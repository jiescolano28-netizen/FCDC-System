<?php

namespace App\Services\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingMapping;
use App\Models\AccountingPostingPeriod;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\OpeningInventoryValuation;
use App\Models\PosTransaction;
use App\Models\PosVatRecord;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosSalePostingService
{
    /**
     * Post a completed checkout using the persisted sale values and the valued-stock schedule.
     * Returns the unit-cost snapshots to persist on immutable POS lines.
     *
     * @param  array<int, array{inventory: Inventory, quantity: float}>  $items
     * @return array<int, string>
     */
    public function post(PosTransaction $sale, array $items, int $actorId): array
    {
        return DB::transaction(function () use ($sale, $items, $actorId): array {
            $actor = Employee::query()->findOrFail($actorId);
            if (! $actor->can('pos.checkout')) {
                abort(403);
            }
            if ($sale->status !== 'completed' || $sale->vatRecord()->count() !== 1) {
                throw ValidationException::withMessages(['accounting' => 'A completed sale with its linked VAT record is required before posting.']);
            }
            if (AccountingJournal::query()->where('source_type', 'pos_sale')->where('source_id', (string) $sale->id)->exists()) {
                throw ValidationException::withMessages(['accounting' => 'This POS sale has already been posted.']);
            }

            $date = CarbonImmutable::parse($sale->completed_at)->timezone('Asia/Manila');
            if ($date->isFuture()) {
                throw ValidationException::withMessages(['accounting' => 'Completed POS sales cannot be future-dated.']);
            }
            $opening = AccountingJournal::query()->where('source_type', 'opening')->where('source_id', 'FCDC')
                ->where('status', 'posted')->first();
            $openingDate = $opening?->accounting_date->toDateString();
            if (! $openingDate || $date->toDateString() < $openingDate) {
                throw ValidationException::withMessages(['accounting' => 'The sale date is outside approved accounting cutover coverage.']);
            }
            $schedule = OpeningInventoryValuation::query()->where('book_key', 'FCDC')->where('status', 'approved')
                ->with('lines')->first();
            if (! $schedule) {
                throw ValidationException::withMessages(['accounting' => 'An approved opening inventory valuation is required before POS posting.']);
            }
            $period = AccountingPostingPeriod::query()->where('book_key', 'FCDC')
                ->whereDate('starts_on', '<=', $date->toDateString())->whereDate('ends_on', '>=', $date->toDateString())
                ->lockForUpdate()->first();
            if (! $period || $period->status !== 'open') {
                throw ValidationException::withMessages(['accounting' => 'The sale date must be in an open accounting period.']);
            }
            $vat = PosVatRecord::query()->where('pos_transaction_id', $sale->id)->firstOrFail();
            $subtotalCents = $this->cents((string) $sale->subtotal);
            $vatCents = $this->cents((string) $sale->vat_amount);
            $totalCents = $this->cents((string) $sale->total);
            if ($subtotalCents < 1 || $vatCents < 0 || $totalCents !== $subtotalCents + $vatCents
                || $this->cents((string) $vat->taxable_sales) !== $subtotalCents
                || $this->cents((string) $vat->output_vat) !== $vatCents
                || $this->cents((string) $vat->total) !== $totalCents) {
                throw ValidationException::withMessages(['accounting' => 'The saved sale totals and linked VAT record do not reconcile.']);
            }

            $cashSource = match ($sale->payment_method) {
                'cash' => 'cash',
                'card' => 'card_clearing',
                'bank_transfer' => 'bank',
                default => throw ValidationException::withMessages(['payment_method' => 'Unsupported POS payment method.']),
            };
            $cashAccount = $this->mappedAccount($cashSource);
            $salesAccount = $this->mappedAccount('sales');
            $vatAccount = $this->mappedAccount('output_vat');
            $cogsAccount = $this->mappedAccount('cogs');
            $inventoryAccount = $this->mappedAccount('inventory');

            $lineCostSnapshots = [];
            $movements = [];
            $cogsCents = 0;
            foreach ($items as $line) {
                $itemId = $line['inventory']->id;
                $quantityHundredths = $this->quantityHundredths((string) $line['quantity']);
                $item = Inventory::query()->lockForUpdate()->findOrFail($itemId);
                if ($item->status !== 'active' || $quantityHundredths < 1) {
                    throw ValidationException::withMessages(['cart' => 'An item is no longer available for valued sale.']);
                }
                $previousQuantity = $this->quantityHundredths((string) $item->qty);
                if ($quantityHundredths > $previousQuantity) {
                    throw ValidationException::withMessages(['cart' => 'The sale quantity exceeds available valued stock.']);
                }
                $latest = StockMovement::query()->where('inventory_id', $item->id)->whereNotNull('value_cents')
                    ->orderByDesc('effective_date')->orderByDesc('posted_at')->orderByDesc('id')->lockForUpdate()->first();
                if ($latest && $date->toDateString() < $latest->effective_date->toDateString()) {
                    throw ValidationException::withMessages(['cart' => 'A sale cannot precede the inventory item’s latest valued movement.']);
                }
                $openingLine = $schedule->lines->firstWhere('inventory_id', $item->id);
                if (! $latest && (! $openingLine || $previousQuantity !== $this->quantityHundredths((string) $openingLine->quantity))) {
                    throw ValidationException::withMessages(['cart' => 'The item has no matching approved opening valuation baseline.']);
                }
                if (StockMovement::query()->where('inventory_id', $item->id)->whereNull('value_cents')
                    ->where('type', '!=', 'opening_balance')->whereDate('effective_date', '>', $openingDate)->exists()) {
                    throw ValidationException::withMessages(['cart' => 'An unvalued post-cutover movement prevents reliable sale valuation.']);
                }
                $previousValue = $latest
                    ? (int) $latest->carrying_value_after_cents
                    : (int) $openingLine->carrying_value_cents;
                if ($previousValue <= 0 || $previousQuantity <= 0) {
                    throw ValidationException::withMessages(['cart' => 'Positive valued stock is required before completing this sale.']);
                }
                if ($latest && $latest->carrying_value_after_cents === null) {
                    throw ValidationException::withMessages(['cart' => 'The latest valued movement has no closing carrying value.']);
                }
                $movementCost = $quantityHundredths === $previousQuantity
                    ? $previousValue
                    : (int) round($previousValue * $quantityHundredths / $previousQuantity);
                if ($movementCost <= 0) {
                    throw ValidationException::withMessages(['cart' => 'The sale cost must be positive and supported by the valued stock schedule.']);
                }
                $nextQuantity = $previousQuantity - $quantityHundredths;
                $nextValue = $previousValue - $movementCost;
                if ($nextValue < 0 || ($nextQuantity === 0 && $nextValue !== 0)) {
                    throw ValidationException::withMessages(['cart' => 'The sale would leave an unsupported inventory carrying value.']);
                }
                $cogsCents += $movementCost;
                $lineCostSnapshots[$item->id] = number_format($previousValue / $previousQuantity, 2, '.', '');
                $movements[] = [
                    'item' => $item,
                    'quantity_hundredths' => $quantityHundredths,
                    'quantity' => $this->decimalFromHundredths($quantityHundredths),
                    'cost_cents' => $movementCost,
                    'next_quantity' => $nextQuantity,
                    'next_value' => $nextValue,
                ];
            }
            if ($cogsCents < 1) {
                throw ValidationException::withMessages(['cart' => 'A POS sale must have a positive valued cost of goods sold.']);
            }

            $journal = AccountingJournal::query()->create([
                'book_key' => 'FCDC',
                'reference' => 'POS-GL-'.$sale->id,
                'source_type' => 'pos_sale',
                'source_id' => (string) $sale->id,
                'accounting_date' => $date->toDateString(),
                'posting_period_id' => $period->id,
                'description' => 'POS sale '.$sale->transaction_number,
                'status' => 'draft',
                'prepared_by' => $actorId,
            ]);
            $journalLines = [
                ['account' => $cashAccount, 'debit' => $totalCents, 'credit' => 0],
                ['account' => $salesAccount, 'debit' => 0, 'credit' => $subtotalCents],
                ['account' => $vatAccount, 'debit' => 0, 'credit' => $vatCents],
                ['account' => $cogsAccount, 'debit' => $cogsCents, 'credit' => 0],
                ['account' => $inventoryAccount, 'debit' => 0, 'credit' => $cogsCents],
            ];
            foreach ($journalLines as $entry) {
                $journal->lines()->create([
                    'accounting_account_id' => $entry['account']->id,
                    'debit_cents' => $entry['debit'],
                    'credit_cents' => $entry['credit'],
                ]);
                $entry['account']->markUsed();
            }
            $journal->forceFill([
                'status' => 'posted', 'posted_at' => now('UTC'), 'posted_by' => $actorId,
                'approved_at' => now('UTC'), 'approved_by' => $actorId,
            ])->save();

            foreach ($movements as $movement) {
                $item = $movement['item'];
                $stockMovement = StockMovement::query()->create([
                    'inventory_id' => $item->id,
                    'posted_by' => $actorId,
                    'type' => 'stock_out',
                    'quantity' => '-'.$movement['quantity'],
                    'reason_category' => 'sale',
                    'reference' => $sale->transaction_number,
                    'source_reference' => 'pos_sale:'.$sale->id,
                    'effective_date' => $date->toDateString(),
                    'posted_at' => now('UTC'),
                    'value_cents' => -$movement['cost_cents'],
                    'carrying_value_after_cents' => $movement['next_value'],
                    'accounting_journal_id' => $journal->id,
                ]);
                Inventory::query()->whereKey($item->id)->update([
                    'qty' => $this->decimalFromHundredths($movement['next_quantity']),
                    'unit_cost' => $movement['next_quantity'] === 0
                        ? '0.00'
                        : number_format($movement['next_value'] / $movement['next_quantity'], 2, '.', ''),
                    'carrying_value_cents' => $movement['next_value'],
                ]);
            }

            return $lineCostSnapshots;
        });
    }

    private function mappedAccount(string $source): AccountingAccount
    {
        $mapping = AccountingPostingMapping::query()->where('source', $source)->with('account')->first();
        if (! $mapping?->isApprovedForPosting()) {
            throw ValidationException::withMessages(['mapping' => "An active approved {$source} posting mapping is required."]);
        }

        return AccountingAccount::query()->lockForUpdate()->findOrFail($mapping->accounting_account_id);
    }

    private function cents(string $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function quantityHundredths(string $quantity): int
    {
        return (int) round((float) $quantity * 100);
    }

    private function decimalFromHundredths(int $quantity): string
    {
        return intdiv($quantity, 100).'.'.str_pad((string) ($quantity % 100), 2, '0', STR_PAD_LEFT);
    }
}
