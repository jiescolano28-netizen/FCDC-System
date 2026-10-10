<?php

namespace App\Services\Inventory;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingPeriod;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CorrectValuedStockMovement
{
    public function correct(
        int $movementId,
        string $reason,
        int $remainingValueDeltaCents,
        int $consumedValueDeltaCents,
        ?int $consumedExpenseAccountId,
        int $actorId,
    ): StockMovement {
        $actor = Employee::query()->findOrFail($actorId);
        abort_unless($actor->can('inventory.movements.record'), 403);
        abort_unless($actor->can('inventory.valuation.approve'), 403);

        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 4000) {
            throw ValidationException::withMessages(['reason' => 'A correction reason of at most 4,000 characters is required.']);
        }

        return DB::transaction(function () use ($movementId, $reason, $remainingValueDeltaCents, $consumedValueDeltaCents, $consumedExpenseAccountId, $actor): StockMovement {
            $source = StockMovement::query()->lockForUpdate()->findOrFail($movementId);
            if ($source->value_cents === null || $source->value_cents <= 0 || $source->quantity <= 0 || $source->accounting_journal_id === null
                || $source->correction_of_movement_id !== null || $source->type !== 'stock_in' || $source->reversal()->exists()
                || $source->cash_disbursement_line_id !== null
                || str_starts_with((string) $source->source_reference, 'supplier_purchase')) {
                throw ValidationException::withMessages(['movement' => 'Only an original non-purchase positively valued receipt can receive a value correction.']);
            }

            $remainingDelta = $remainingValueDeltaCents;
            $consumedDelta = $consumedValueDeltaCents;
            if (($remainingDelta < 0 || $consumedDelta < 0) && ($remainingDelta > 0 || $consumedDelta > 0)) {
                throw ValidationException::withMessages(['valuation' => 'A correction cannot increase one cost portion while decreasing another.']);
            }
            $totalDelta = $remainingDelta + $consumedDelta;
            if ($totalDelta === 0) {
                throw ValidationException::withMessages(['valuation' => 'The value correction must change the source value.']);
            }

            $item = Inventory::query()->lockForUpdate()->findOrFail($source->inventory_id);
            $quantityHundredths = (int) round((float) $item->qty * 100);
            $latest = StockMovement::query()->where('inventory_id', $item->id)->whereNotNull('value_cents')
                ->orderByDesc('effective_date')->orderByDesc('posted_at')->orderByDesc('id')->first();
            if (! $latest) {
                throw ValidationException::withMessages(['movement' => 'The stock source has no current valued balance.']);
            }
            $date = CarbonImmutable::now('Asia/Manila')->startOfDay();
            if ($latest->effective_date->toDateString() > $date->toDateString()) {
                throw ValidationException::withMessages(['effectiveDate' => 'A value correction cannot precede a later effective stock movement.']);
            }
            $latestValue = (int) $latest->carrying_value_after_cents;
            if ($remainingDelta < 0 && abs($remainingDelta) > $latestValue) {
                throw ValidationException::withMessages(['remainingValue' => 'The remaining-value reduction exceeds current inventory carrying value.']);
            }
            $priorCorrectionDelta = (int) StockMovement::query()->where('correction_of_movement_id', $source->id)->sum('correction_total_delta_cents');
            if ((int) $source->value_cents + $priorCorrectionDelta + $totalDelta < 0) {
                throw ValidationException::withMessages(['valuation' => 'The correction exceeds the remaining value of its permitted source receipt.']);
            }
            $nextCarryingValue = $latestValue + $remainingDelta;
            if ($nextCarryingValue < 0 || ($quantityHundredths === 0 && $nextCarryingValue !== 0)) {
                throw ValidationException::withMessages(['remainingValue' => 'The correction would create unsupported negative or residual inventory value.']);
            }
            if ($consumedDelta !== 0 && $consumedExpenseAccountId === null) {
                throw ValidationException::withMessages(['consumedExpenseAccountId' => 'Select an approved expense account for consumed-value corrections.']);
            }

            $period = AccountingPostingPeriod::query()->where('book_key', 'FCDC')
                ->whereDate('starts_on', '<=', $date->toDateString())->whereDate('ends_on', '>=', $date->toDateString())
                ->lockForUpdate()->first();
            if (! $period || $period->status !== 'open') {
                throw ValidationException::withMessages(['effectiveDate' => 'Value corrections require the current open accounting period.']);
            }
            $originalJournal = AccountingJournal::query()->with('lines')->findOrFail($source->accounting_journal_id);
            $inventoryAccountLine = $originalJournal->lines->first(fn ($line) => $line->account->classification === 'inventory');
            $counterpartLine = $originalJournal->lines->first(fn ($line) => $line->accounting_account_id !== $inventoryAccountLine?->accounting_account_id);
            if (! $inventoryAccountLine || ! $counterpartLine) {
                throw ValidationException::withMessages(['accounting' => 'The original source journal must identify Inventory and its approved counterpart.']);
            }
            $counterpart = AccountingAccount::query()->lockForUpdate()->findOrFail($counterpartLine->accounting_account_id);
            if (! $counterpart->isApprovedForPosting()) {
                throw ValidationException::withMessages(['accounting' => 'The original approved counterpart must remain active and approved.']);
            }
            $consumedAccount = null;
            if ($consumedDelta !== 0) {
                $consumedAccount = AccountingAccount::query()->lockForUpdate()->findOrFail($consumedExpenseAccountId);
                if (! $consumedAccount->isApprovedForPosting() || $consumedAccount->type !== 'Expense') {
                    throw ValidationException::withMessages(['consumedExpenseAccountId' => 'Consumed-value treatment requires an active approved expense account.']);
                }
            }

            $journalLines = [];
            $this->addAdjustment($journalLines, $inventoryAccountLine->accounting_account_id, $remainingDelta);
            if ($consumedAccount) {
                $this->addAdjustment($journalLines, $consumedAccount->id, $consumedDelta);
            }
            $this->addAdjustment($journalLines, $counterpart->id, -$totalDelta);
            $debits = array_sum(array_column($journalLines, 'debit_cents'));
            $credits = array_sum(array_column($journalLines, 'credit_cents'));
            if ($debits < 1 || $debits !== $credits) {
                throw ValidationException::withMessages(['accounting' => 'The value correction does not produce balanced journal entries.']);
            }

            $journal = AccountingJournal::query()->create([
                'book_key' => 'FCDC',
                'reference' => 'STK-CORR-'.$source->id.'-'.Str::upper(Str::random(8)),
                'source_type' => 'stock_valuation_correction',
                'source_id' => (string) $source->id,
                'accounting_date' => $date->toDateString(),
                'posting_period_id' => $period->id,
                'description' => 'Valuation correction for stock movement #'.$source->id.': '.$reason,
                'correction_of_id' => $originalJournal->id,
                'correction_reason' => $reason,
                'status' => 'draft',
                'prepared_by' => $actor->id,
            ]);
            foreach ($journalLines as $accountId => $amounts) {
                if ($amounts['debit_cents'] === 0 && $amounts['credit_cents'] === 0) {
                    continue;
                }
                $journal->lines()->create(['accounting_account_id' => $accountId, ...$amounts]);
                AccountingAccount::query()->whereKey($accountId)->firstOrFail()->markUsed();
            }
            $journal->forceFill([
                'status' => 'posted', 'posted_by' => $actor->id, 'posted_at' => now('UTC'),
                'approved_by' => $actor->id, 'approved_at' => now('UTC'),
            ])->save();

            $correction = StockMovement::query()->create([
                'inventory_id' => $item->id,
                'demolition_project_id' => $source->demolition_project_id,
                'recovered_material_id' => $source->recovered_material_id,
                'posted_by' => $actor->id,
                'type' => 'valuation_correction',
                'quantity' => '0.00',
                'reason_category' => 'valuation_correction',
                'notes' => $reason,
                'reference' => 'STK-CORR-'.$source->id,
                'source_reference' => 'stock_valuation_correction:'.$source->id,
                'effective_date' => $date->toDateString(),
                'posted_at' => now('UTC'),
                'value_cents' => $remainingDelta,
                'carrying_value_after_cents' => $nextCarryingValue,
                'accounting_journal_id' => $journal->id,
                'correction_of_movement_id' => $source->id,
                'correction_reason' => $reason,
                'correction_total_delta_cents' => $totalDelta,
            ]);
            Inventory::query()->whereKey($item->id)->update([
                'carrying_value_cents' => $nextCarryingValue,
                'unit_cost' => $quantityHundredths === 0 ? '0.00' : number_format($nextCarryingValue / $quantityHundredths, 2, '.', ''),
            ]);

            return $correction->refresh();
        });
    }

    private function addAdjustment(array &$lines, int $accountId, int $signedAmount): void
    {
        $lines[$accountId] ??= ['debit_cents' => 0, 'credit_cents' => 0];
        if ($signedAmount >= 0) {
            $lines[$accountId]['debit_cents'] += $signedAmount;
        } else {
            $lines[$accountId]['credit_cents'] += abs($signedAmount);
        }
    }
}
