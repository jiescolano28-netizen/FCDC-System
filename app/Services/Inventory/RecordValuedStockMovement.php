<?php

namespace App\Services\Inventory;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingMapping;
use App\Models\AccountingPostingPeriod;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\OpeningInventoryValuation;
use App\Models\RecoveredMaterial;
use App\Models\RecoveredMaterialAssessment;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordValuedStockMovement
{
    public function handle(
        int $inventoryId,
        string $type,
        string $quantity,
        string $reason,
        string $sourceReference,
        string $effectiveDate,
        int $actorId,
        ?string $receiptUnitValue = null,
        ?string $notes = null,
        ?int $demolitionProjectId = null,
        ?int $recoveredMaterialId = null,
        ?Closure $sourceLink = null,
    ): StockMovement {
        $actor = Employee::query()->findOrFail($actorId);
        if (! $actor->can('inventory.movements.record')) {
            abort(403);
        }
        if (! in_array($type, ['stock_in', 'stock_out', 'adjustment'], true)) {
            throw ValidationException::withMessages(['type' => 'Unsupported valued stock movement.']);
        }
        $allowedReasons = $type === 'stock_in'
            ? ['purchase_receipt', 'return', 'recovered_material', 'other']
            : ['project_use', 'damage_loss', 'count_correction', 'other'];
        if (! in_array($reason, $allowedReasons, true)) {
            throw ValidationException::withMessages(['reason' => 'Select a valid reason for this valued movement.']);
        }
        if (($reason === 'recovered_material') !== ($demolitionProjectId !== null && $recoveredMaterialId !== null)) {
            throw ValidationException::withMessages(['sourceReference' => 'Recovered-material receipts require linked project and recovery records.']);
        }
        if (($demolitionProjectId === null) !== ($recoveredMaterialId === null)) {
            throw ValidationException::withMessages(['sourceReference' => 'Both project and recovery links are required.']);
        }
        if (($reason === 'recovered_material') !== ($sourceLink !== null)) {
            throw ValidationException::withMessages(['sourceReference' => 'Recovered-material receipts must be posted with their immutable assessment in one transaction.']);
        }
        if (trim($sourceReference) === '' || strlen($sourceReference) > 255) {
            throw ValidationException::withMessages(['sourceReference' => 'A source reference of at most 255 characters is required.']);
        }
        if ($type === 'stock_in' && ! $actor->can('inventory.valuation.approve')) {
            abort(403);
        }

        return DB::transaction(function () use ($inventoryId, $type, $quantity, $reason, $sourceReference, $effectiveDate, $actorId, $actor, $receiptUnitValue, $notes, $demolitionProjectId, $recoveredMaterialId, $sourceLink): StockMovement {
            $item = Inventory::query()->lockForUpdate()->findOrFail($inventoryId);
            if ($recoveredMaterialId !== null) {
                $recovery = RecoveredMaterial::query()->lockForUpdate()->findOrFail($recoveredMaterialId);
                if ($recovery->demolition_project_id !== $demolitionProjectId) {
                    throw ValidationException::withMessages(['sourceReference' => 'The recovered material does not belong to the supplied project.']);
                }
            }

            if ($item->status !== 'active') {
                throw ValidationException::withMessages(['inventoryId' => 'Inactive inventory items cannot be valued.']);
            }

            $date = $this->validPostingDate($effectiveDate);
            $opening = AccountingJournal::query()->where('source_type', 'opening')->where('source_id', 'FCDC')
                ->where('status', 'posted')->first();
            $schedule = OpeningInventoryValuation::query()->where('book_key', 'FCDC')->where('status', 'approved')
                ->with('lines')->first();
            if (! $opening || ! $schedule || $date->toDateString() < $opening->accounting_date->toDateString()) {
                throw ValidationException::withMessages(['effectiveDate' => 'Valued stock posting requires approved opening books and an effective date on or after cutover.']);
            }
            $openingLine = $schedule->lines->firstWhere('inventory_id', $item->id);
            if (! $openingLine) {
                if ($item->created_at->timezone('Asia/Manila')->toDateString() < $opening->accounting_date->toDateString() || $this->quantityHundredths((string) $item->qty) !== 0) {
                    throw ValidationException::withMessages(['inventoryId' => 'This item has no approved opening valuation baseline.']);
                }
            }
            $latest = StockMovement::query()->where('inventory_id', $item->id)->whereNotNull('value_cents')
                ->orderByDesc('effective_date')->orderByDesc('posted_at')->orderByDesc('id')->first();
            if ($latest && $date->toDateString() < $latest->effective_date->toDateString()) {
                throw ValidationException::withMessages(['effectiveDate' => 'Valued movements cannot be backdated before the latest valued movement for this item.']);
            }

            $quantityHundredths = $this->quantityHundredths($quantity);
            $previousQuantity = $latest
                ? $this->quantityHundredths((string) $item->qty)
                : $this->quantityHundredths((string) ($openingLine?->quantity ?? '0'));
            $previousValue = $latest
                ? (int) $latest->carrying_value_after_cents
                : (int) ($openingLine?->carrying_value_cents ?? 0);

            $isIncrease = $type === 'stock_in';
            $delta = $type === 'adjustment'
                ? $quantityHundredths - $previousQuantity
                : ($isIncrease ? $quantityHundredths : -$quantityHundredths);
            if ($type === 'adjustment') {
                $quantityHundredths = abs($delta);
            }
            $nextQuantity = $previousQuantity + $delta;
            if ($delta === 0 || $nextQuantity < 0) {
                throw ValidationException::withMessages(['quantity' => 'The valued movement must change quantity and cannot leave a negative balance.']);
            }
            if ($delta > 0 && ! $actor->can('inventory.valuation.approve')) {
                abort(403);
            }

            if ($delta > 0) {
                if ($receiptUnitValue === null || trim($receiptUnitValue) === '') {
                    throw ValidationException::withMessages(['receiptUnitValue' => 'An approved unit value is required for every stock increase.']);
                }
                $unitValueCents = $this->amountCents($receiptUnitValue);
                if ($unitValueCents < 0) {
                    throw ValidationException::withMessages(['receiptUnitValue' => 'Approved unit value cannot be negative.']);
                }
                $movementValue = $nextQuantity === 0
                    ? 0
                    : (int) round($delta * $unitValueCents / 100);
                $counterpartSource = 'recovery_offset';
                $signedValue = $movementValue;
            } else {
                $movementValue = $nextQuantity === 0
                    ? $previousValue
                    : (int) round($previousValue * abs($delta) / $previousQuantity);
                $counterpartSource = 'adjustment';
                $signedValue = -$movementValue;
            }
            $nextValue = $previousValue + $signedValue;
            if ($nextValue < 0 || ($nextQuantity === 0 && $nextValue !== 0)) {
                throw ValidationException::withMessages(['quantity' => 'The movement would produce an unsupported inventory carrying value.']);
            }

            $counterpartMapping = AccountingPostingMapping::query()->where('source', $counterpartSource)->with('account')->first();
            $inventoryMapping = AccountingPostingMapping::query()->where('source', 'inventory')->with('account')->first();
            if (! $counterpartMapping?->isApprovedForPosting() || ! $inventoryMapping?->isApprovedForPosting()) {
                throw ValidationException::withMessages(['accounting' => 'Approved active Inventory and movement-counterpart mappings are required.']);
            }
            $counterpart = $counterpartMapping->account;
            $inventoryAccount = $inventoryMapping->account;
            if (! $counterpart instanceof AccountingAccount || ! $inventoryAccount instanceof AccountingAccount) {
                throw ValidationException::withMessages(['accounting' => 'The approved movement mappings are incomplete.']);
            }
            if ($counterpart->id === $inventoryAccount->id
                || in_array($counterpart->classification, ['inventory', 'accounts_payable', 'sales', 'cost_of_goods_sold', 'output_vat'], true)
                || ($counterpartSource === 'adjustment' && $counterpart->type !== 'Expense')) {
                throw ValidationException::withMessages(['accounting' => 'The approved counterpart cannot be Inventory, AP, Sales, COGS or Output VAT; decreases must use an Expense account.']);
            }

            $period = AccountingPostingPeriod::query()->where('book_key', 'FCDC')
                ->whereDate('starts_on', '<=', $date->toDateString())->whereDate('ends_on', '>=', $date->toDateString())
                ->lockForUpdate()->first();
            if (! $period || $period->status !== 'open') {
                throw ValidationException::withMessages(['effectiveDate' => 'The effective date must be in an open accounting period.']);
            }

            $movement = StockMovement::create([
                'inventory_id' => $item->id,
                'demolition_project_id' => $demolitionProjectId,
                'recovered_material_id' => $recoveredMaterialId,
                'posted_by' => $actorId,
                'type' => $type,
                'quantity' => number_format($delta / 100, 2, '.', ''),
                'reason_category' => $reason,
                'notes' => $notes,
                'reference' => $sourceReference,
                'source_reference' => $sourceReference,
                'effective_date' => $date->toDateString(),
                'posted_at' => now('UTC'),
                'value_cents' => $signedValue,
                'carrying_value_after_cents' => $nextValue,
            ]);

            $reference = 'STK-'.$movement->id;
            $journal = AccountingJournal::create([
                'book_key' => 'FCDC',
                'reference' => $reference,
                'source_type' => 'stock_movement',
                'source_id' => (string) $movement->id,
                'accounting_date' => $date->toDateString(),
                'posting_period_id' => $period->id,
                'description' => ucfirst(str_replace('_', ' ', $reason)).' · '.$item->code.' · '.$sourceReference,
                'status' => 'draft',
                'prepared_by' => $actorId,
            ]);
            $debitAccountId = $signedValue > 0 ? $inventoryAccount->id : $counterpart->id;
            $creditAccountId = $signedValue > 0 ? $counterpart->id : $inventoryAccount->id;
            $amount = abs($signedValue);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['valuation' => 'A valued stock movement must post a positive accounting amount.']);
            }
            $journal->lines()->createMany([
                ['accounting_account_id' => $debitAccountId, 'debit_cents' => $amount, 'credit_cents' => 0],
                ['accounting_account_id' => $creditAccountId, 'debit_cents' => 0, 'credit_cents' => $amount],
            ]);
            $journal->forceFill([
                'status' => 'posted',
                'posted_at' => now('UTC'),
                'posted_by' => $actorId,
                'approved_at' => now('UTC'),
                'approved_by' => $actorId,
            ])->save();
            $movement->forceFill(['accounting_journal_id' => $journal->id])->saveQuietly();
            if ($sourceLink !== null) {
                $assessment = $sourceLink($movement, $counterpart, $unitValueCents, $movementValue);
                if (! $assessment instanceof RecoveredMaterialAssessment
                    || $assessment->stock_movement_id !== $movement->id
                    || $assessment->recovered_material_id !== $recoveredMaterialId) {
                    throw ValidationException::withMessages(['assessment' => 'A valued recovery receipt must atomically create its linked immutable assessment.']);
                }
            }
            Inventory::query()->whereKey($item->id)->update([
                'qty' => number_format($nextQuantity / 100, 2, '.', ''),
                'unit_cost' => $nextQuantity === 0 ? '0.00' : number_format($nextValue / $nextQuantity, 2, '.', ''),
                'carrying_value_cents' => $nextValue,
            ]);
            $inventoryAccount->markUsed();
            $counterpart->markUsed();

            return $movement->refresh();
        });
    }


    private function validPostingDate(string $date): CarbonImmutable
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Manila');
        if (! $parsed || $parsed->format('Y-m-d') !== $date || $parsed->isFuture()) {
            throw ValidationException::withMessages(['effectiveDate' => 'Effective date must be a valid, non-future Manila business date.']);
        }

        return $parsed;
    }

    private function quantityHundredths(string $quantity): int
    {
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/D', trim($quantity))) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be non-negative with at most two decimal places.']);
        }

        return (int) round((float) $quantity * 100);
    }

    private function amountCents(string $amount): int
    {
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/D', trim($amount))) {
            throw ValidationException::withMessages(['receiptUnitValue' => 'Approved unit value must have at most two decimal places.']);
        }

        return (int) round((float) $amount * 100);
    }
}
