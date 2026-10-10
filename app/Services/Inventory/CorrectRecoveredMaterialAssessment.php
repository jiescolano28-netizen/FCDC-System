<?php

namespace App\Services\Inventory;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\RecoveredMaterial;
use App\Models\RecoveredMaterialAssessment;
use App\Models\StockMovement;
use App\Services\Accounting\AccountingPeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CorrectRecoveredMaterialAssessment
{
    public function handle(
        RecoveredMaterial $recovery,
        RecoveredMaterialAssessment $prior,
        array $input,
        int $actorId,
    ): RecoveredMaterialAssessment {
        $actor = Employee::query()->findOrFail($actorId);
        abort_unless($actor->can('demolition-projects.manage'), 403);
        abort_unless($actor->can('inventory.movements.record'), 403);
        abort_unless($actor->can('inventory.valuation.approve'), 403);

        $reason = trim((string) ($input['correction_reason'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > 4000) {
            throw ValidationException::withMessages(['assessmentCorrectionReason' => 'A correction reason of at most 4,000 characters is required.']);
        }
        $acceptedHundredths = $this->quantityHundredths((string) ($input['accepted_quantity'] ?? ''));
        $rejectedHundredths = $this->quantityHundredths((string) ($input['rejected_quantity'] ?? ''));
        if ($acceptedHundredths < 0 || $rejectedHundredths < 0
            || $acceptedHundredths + $rejectedHundredths !== $this->quantityHundredths((string) $recovery->quantity)) {
            throw ValidationException::withMessages(['acceptedQuantity' => 'Accepted and rejected quantities must equal the recovered quantity.']);
        }
        if ($rejectedHundredths > 0 && trim((string) ($input['rejection_reason'] ?? '')) === '') {
            throw ValidationException::withMessages(['rejectionReason' => 'A reason is required for rejected material.']);
        }

        $priorAcceptedHundredths = $this->quantityHundredths((string) $prior->accepted_quantity);
        if ($acceptedHundredths === $priorAcceptedHundredths) {
            throw ValidationException::withMessages(['acceptedQuantity' => 'A reassessment must change the accepted quantity; value-only corrections belong in the valued-stock history.']);
        }
        if ($prior->stock_movement_id === null || $prior->assigned_unit_value_cents === null
            || $prior->counterpart_accounting_account_id === null) {
            throw ValidationException::withMessages(['assessment' => 'This recovery has no valued accepted receipt to correct.']);
        }
        $item = Inventory::query()->lockForUpdate()->findOrFail($prior->inventory_id);
        if ($item->status !== 'active' || $item->unit !== $recovery->unit) {
            throw ValidationException::withMessages(['inventoryId' => 'The original active inventory item and recovery unit must remain unchanged.']);
        }
        $source = StockMovement::query()->lockForUpdate()->findOrFail($prior->stock_movement_id);
        $latest = StockMovement::query()->where('inventory_id', $item->id)->whereNotNull('value_cents')
            ->orderByDesc('effective_date')->orderByDesc('posted_at')->orderByDesc('id')->first();
        if (! $latest || $latest->id !== $source->id) {
            throw ValidationException::withMessages(['movement' => 'The recovery receipt has later valued movements; physical reassessment cannot rewrite consumed stock or bypass chronology. Correct remaining and consumed value through a reviewed value correction.']);
        }

        return DB::transaction(function () use ($recovery, $prior, $input, $actor, $reason, $acceptedHundredths, $rejectedHundredths, $priorAcceptedHundredths, $item, $source): RecoveredMaterialAssessment {
            $date = CarbonImmutable::now('Asia/Manila')->startOfDay();
            $period = app(AccountingPeriodService::class)->lockOpenPeriodForDate(
                $date->toDateString(),
                'assessment',
                'Recovery corrections require the current open accounting period.',
            );
            if ($source->effective_date->toDateString() > $date->toDateString()) {
                throw ValidationException::withMessages(['assessment' => 'A recovery correction cannot precede its original valued receipt.']);
            }
            $quantityDelta = $acceptedHundredths - $priorAcceptedHundredths;
            $quantityDifference = abs($quantityDelta);
            $valueCents = (int) round($quantityDifference * (int) $prior->assigned_unit_value_cents / 100);
            if ($valueCents <= 0 || ($quantityDelta < 0 && $valueCents > (int) $prior->assigned_value_cents)) {
                throw ValidationException::withMessages(['assessment' => 'The physical reassessment exceeds the value of the latest accepted assessment.']);
            }
            $currentQuantity = (int) round((float) $item->qty * 100);
            $currentValue = (int) $item->carrying_value_cents;
            $nextQuantity = $currentQuantity + $quantityDelta;
            $nextValue = $currentValue + ($quantityDelta > 0 ? $valueCents : -$valueCents);
            if (($quantityDelta < 0 && $currentQuantity < $quantityDifference) || $nextQuantity < 0 || $nextValue < 0
                || ($nextQuantity === 0 && $nextValue !== 0)) {
                throw ValidationException::withMessages(['assessment' => 'The corrected recovery exceeds available stock or carrying value.']);
            }

            $counterpart = AccountingAccount::query()->lockForUpdate()->findOrFail($prior->counterpart_accounting_account_id);
            if (! $counterpart->isApprovedForPosting()) {
                throw ValidationException::withMessages(['accounting' => 'The original approved recovery counterpart must remain active and approved.']);
            }
            $originalJournal = AccountingJournal::query()->with('lines')->findOrFail($source->accounting_journal_id);
            $inventoryLine = $originalJournal->lines->first(fn ($line) => $line->account->classification === 'inventory');
            if (! $inventoryLine || $inventoryLine->accounting_account_id === $counterpart->id) {
                throw ValidationException::withMessages(['accounting' => 'The original recovery journal must identify distinct Inventory and recovery-counterpart accounts.']);
            }

            $movement = StockMovement::query()->create([
                'inventory_id' => $item->id,
                'demolition_project_id' => $recovery->demolition_project_id,
                'recovered_material_id' => $recovery->id,
                'posted_by' => $actor->id,
                'type' => 'recovery_correction',
                'quantity' => number_format($quantityDelta / 100, 2, '.', ''),
                'reason_category' => 'recovered_material_correction',
                'notes' => $reason,
                'reference' => 'REC-CORR-'.$recovery->id.'-'.$source->id,
                'source_reference' => 'recovered_material_correction:'.$recovery->id,
                'effective_date' => $date->toDateString(),
                'posted_at' => now('UTC'),
                'value_cents' => $quantityDelta > 0 ? $valueCents : -$valueCents,
                'carrying_value_after_cents' => $nextValue,
                'correction_of_movement_id' => $source->id,
                'correction_reason' => $reason,
                'correction_total_delta_cents' => $quantityDelta > 0 ? $valueCents : -$valueCents,
            ]);
            $journal = AccountingJournal::query()->create([
                'book_key' => 'FCDC',
                'reference' => 'REC-CORR-'.$recovery->id.'-'.$movement->id.'-'.Str::upper(Str::random(6)),
                'source_type' => 'recovered_material_correction',
                'source_id' => (string) $movement->id,
                'accounting_date' => $date->toDateString(),
                'posting_period_id' => $period->id,
                'description' => 'Recovery reassessment #'.$recovery->id.': '.$reason,
                'external_reference' => 'Recovery #'.$recovery->id,
                'correction_of_id' => $originalJournal->id,
                'correction_reason' => $reason,
                'status' => 'draft',
                'prepared_by' => $actor->id,
            ]);
            $inventoryAccountId = $inventoryLine->accounting_account_id;
            $journal->lines()->createMany($quantityDelta > 0 ? [
                ['accounting_account_id' => $inventoryAccountId, 'debit_cents' => $valueCents, 'credit_cents' => 0],
                ['accounting_account_id' => $counterpart->id, 'debit_cents' => 0, 'credit_cents' => $valueCents],
            ] : [
                ['accounting_account_id' => $counterpart->id, 'debit_cents' => $valueCents, 'credit_cents' => 0],
                ['accounting_account_id' => $inventoryAccountId, 'debit_cents' => 0, 'credit_cents' => $valueCents],
            ]);
            $journal->forceFill([
                'status' => 'posted', 'posted_at' => now('UTC'), 'posted_by' => $actor->id,
                'approved_at' => now('UTC'), 'approved_by' => $actor->id,
            ])->save();
            $movement->forceFill(['accounting_journal_id' => $journal->id])->saveQuietly();
            Inventory::query()->whereKey($item->id)->update([
                'qty' => number_format($nextQuantity / 100, 2, '.', ''),
                'carrying_value_cents' => $nextValue,
                'unit_cost' => $nextQuantity === 0 ? '0.00' : number_format($nextValue / $nextQuantity, 2, '.', ''),
            ]);
            $counterpart->markUsed();
            $inventoryLine->account->markUsed();

            return RecoveredMaterialAssessment::query()->create([
                'recovered_material_id' => $recovery->id,
                'inventory_id' => $item->id,
                'assessed_by' => $actor->id,
                'accepted_quantity' => number_format($acceptedHundredths / 100, 2, '.', ''),
                'rejected_quantity' => number_format($rejectedHundredths / 100, 2, '.', ''),
                'rejection_reason' => $rejectedHundredths > 0 ? trim((string) $input['rejection_reason']) : null,
                'supersedes_assessment_id' => $prior->id,
                'stock_movement_id' => $movement->id,
                'assigned_unit_value_cents' => $prior->assigned_unit_value_cents,
                'assigned_value_cents' => (int) round($acceptedHundredths * (int) $prior->assigned_unit_value_cents / 100),
                'counterpart_accounting_account_id' => $counterpart->id,
                'valuation_approved_by' => $actor->id,
                'valuation_approved_at' => now('UTC'),
            ]);
        });
    }

    private function quantityHundredths(string $quantity): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', trim($quantity), $parts)) {
            throw ValidationException::withMessages(['acceptedQuantity' => 'Quantities must be non-negative and use at most two decimal places.']);
        }

        return (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
    }
}
