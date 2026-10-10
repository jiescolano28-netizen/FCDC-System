<?php

namespace App\Services\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingMapping;
use App\Models\AccountingPostingPeriod;
use App\Models\Inventory;
use App\Models\OpeningInventoryValuation;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierPurchaseInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SupplierPurchaseService
{
    public function saveDraft(array $input, int $actorId): SupplierPurchaseInvoice
    {
        $data = Validator::make($input, [
            'id' => ['nullable', 'integer', 'exists:supplier_purchase_invoices,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'invoice_number' => ['required', 'string', 'max:100'],
            'recognition_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'description' => ['required', 'string', 'max:4000'],
            'terms' => ['nullable', 'string', 'max:180'],
            'receipt_confirmed' => ['required', 'boolean'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.inventory_id' => ['nullable', 'integer', 'exists:inventories,id'],
            'lines.*.accounting_account_id' => ['nullable', 'integer', 'exists:accounting_accounts,id'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['nullable', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'lines.*.amount' => ['required', 'regex:/^\d+(?:\.\d{1,2})?$/'],
        ])->validate();
        $number = trim($data['invoice_number']);
        if ($number === '') {
            throw ValidationException::withMessages(['invoice_number' => 'Supplier invoice number is required.']);
        }
        $recognition = CarbonImmutable::createFromFormat('!Y-m-d', $data['recognition_date'], 'Asia/Manila');
        $due = CarbonImmutable::createFromFormat('!Y-m-d', $data['due_date'], 'Asia/Manila');
        if ($due->lt($recognition)) {
            throw ValidationException::withMessages(['due_date' => 'Due date cannot be before recognition date.']);
        }
        $lines = [];
        $total = 0;
        foreach ($data['lines'] as $index => $line) {
            $inventoryId = isset($line['inventory_id']) && $line['inventory_id'] !== '' ? (int) $line['inventory_id'] : null;
            $accountId = isset($line['accounting_account_id']) && $line['accounting_account_id'] !== '' ? (int) $line['accounting_account_id'] : null;
            if (($inventoryId === null) === ($accountId === null)) {
                throw ValidationException::withMessages(["lines.$index" => 'Choose exactly one inventory item or non-inventory account.']);
            }
            $amount = $this->cents((string) $line['amount']);
            if ($amount < 1 || $total > PHP_INT_MAX - $amount) {
                throw ValidationException::withMessages(["lines.$index.amount" => 'Each allocation must be positive and the invoice total must fit the supported centavo range.']);
            }
            $quantity = null;
            if ($inventoryId !== null) {
                $quantity = $this->quantityHundredths((string) ($line['quantity'] ?? ''));
                if ($quantity < 1) {
                    throw ValidationException::withMessages(["lines.$index.quantity" => 'Inventory receipt quantity must be positive.']);
                }
            } elseif (! empty($line['quantity'])) {
                throw ValidationException::withMessages(["lines.$index.quantity" => 'Non-inventory allocations cannot carry a stock quantity.']);
            }
            $lines[] = [
                'inventory_id' => $inventoryId,
                'accounting_account_id' => $accountId,
                'description' => trim($line['description']),
                'quantity' => $quantity === null ? null : number_format($quantity / 100, 2, '.', ''),
                'line_amount_cents' => $amount,
            ];
            $total += $amount;
        }
        if ($total < 1) {
            throw ValidationException::withMessages(['lines' => 'Invoice total must be positive.']);
        }
        $normalized = mb_strtoupper($number, 'UTF-8');

        return DB::transaction(function () use ($data, $actorId, $number, $normalized, $total, $lines): SupplierPurchaseInvoice {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($data['supplier_id']);
            $invoice = isset($data['id'])
                ? SupplierPurchaseInvoice::query()->lockForUpdate()->findOrFail($data['id'])
                : new SupplierPurchaseInvoice;
            if ($invoice->exists && $invoice->status !== 'draft') {
                throw ValidationException::withMessages(['invoice_number' => 'Posted supplier invoices are immutable.']);
            }
            $duplicate = SupplierPurchaseInvoice::query()->where('supplier_id', $supplier->id)
                ->where('invoice_number_normalized', $normalized)
                ->when($invoice->exists, fn ($query) => $query->whereKeyNot($invoice->id))->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['invoice_number' => 'This normalized invoice number already exists for this supplier.']);
            }
            $invoice->fill([
                'supplier_id' => $supplier->id,
                'supplier_code_snapshot' => $supplier->code,
                'supplier_name_snapshot' => $supplier->name,
                'invoice_number' => $number,
                'invoice_number_normalized' => $normalized,
                'recognition_date' => $data['recognition_date'],
                'due_date' => $data['due_date'],
                'gross_amount_cents' => $total,
                'description' => trim($data['description']),
                'terms' => trim($data['terms'] ?? '') ?: null,
                'receipt_confirmed' => (bool) $data['receipt_confirmed'],
                'status' => 'draft',
                'prepared_by' => $actorId,
            ])->save();
            $invoice->lines()->delete();
            $invoice->lines()->createMany($lines);

            return $invoice->refresh()->load('lines');
        });
    }

    public function deleteDraft(int $invoiceId): void
    {
        DB::transaction(function () use ($invoiceId): void {
            $invoice = SupplierPurchaseInvoice::query()->lockForUpdate()->findOrFail($invoiceId);
            if ($invoice->status !== 'draft') {
                throw ValidationException::withMessages(['invoice' => 'Posted supplier invoices cannot be deleted.']);
            }
            $invoice->delete();
        });
    }

    public function post(int $invoiceId, int $actorId): SupplierPurchaseInvoice
    {
        return DB::transaction(function () use ($invoiceId, $actorId): SupplierPurchaseInvoice {
            $invoice = SupplierPurchaseInvoice::query()->with('lines')->lockForUpdate()->findOrFail($invoiceId);
            if ($invoice->status !== 'draft') {
                throw ValidationException::withMessages(['invoice' => 'Only a draft supplier invoice can be posted.']);
            }
            if (! $invoice->receipt_confirmed) {
                throw ValidationException::withMessages(['receipt_confirmed' => 'Confirm physical receipt before posting the supplier invoice.']);
            }
            $date = $invoice->recognition_date->toDateString();
            $dateValue = CarbonImmutable::parse($date, 'Asia/Manila');
            if ($dateValue->isFuture()) {
                throw ValidationException::withMessages(['recognition_date' => 'Completed purchases cannot be future-dated.']);
            }
            $cutover = AccountingJournal::query()->where('source_type', 'opening')->where('source_id', 'FCDC')
                ->where('status', 'posted')->value('accounting_date');
            if (! $cutover || $dateValue->lt(CarbonImmutable::parse($cutover, 'Asia/Manila'))) {
                throw ValidationException::withMessages(['recognition_date' => 'Purchase date is outside approved accounting cutover coverage.']);
            }
            $period = AccountingPostingPeriod::query()->firstOrCreate(
                ['book_key' => 'FCDC', 'fiscal_year' => (int) $dateValue->format('Y')],
                ['starts_on' => $dateValue->startOfYear()->toDateString(), 'ends_on' => $dateValue->endOfYear()->toDateString(), 'status' => 'open'],
            );
            $period = AccountingPostingPeriod::query()->lockForUpdate()->findOrFail($period->id);
            if ($period->status !== 'open') {
                throw ValidationException::withMessages(['recognition_date' => 'The purchase recognition date is in a closed accounting period.']);
            }
            $ap = $this->mappedAccount('accounts_payable');
            $inventoryAccount = $invoice->lines->contains(fn ($line) => $line->inventory_id !== null)
                ? $this->mappedAccount('inventory')
                : null;
            $journalLines = [];
            $debits = 0;
            foreach ($invoice->lines as $line) {
                $account = $line->inventory_id ? $inventoryAccount : AccountingAccount::query()->lockForUpdate()->find($line->accounting_account_id);
                if (! $account?->isApprovedForPosting()) {
                    throw ValidationException::withMessages(['lines' => 'Every acquired asset, expense, and inventory mapping must be active and approved.']);
                }
                if (! $line->inventory_id && (! in_array($account->type, ['Asset', 'Expense'], true)
                    || in_array($account->classification, ['accounts_payable', 'inventory', 'input_vat', 'output_vat'], true))) {
                    throw ValidationException::withMessages(['lines' => 'Non-inventory allocations require an approved acquired asset or expense account; VAT and control accounts cannot be inferred.']);
                }
                $amount = (int) $line->line_amount_cents;
                $journalLines[] = ['account' => $account, 'debit' => $amount, 'credit' => 0];
                $debits += $amount;
            }
            if ($debits !== (int) $invoice->gross_amount_cents || $debits < 1) {
                throw ValidationException::withMessages(['lines' => 'Purchase allocations must equal the gross invoice amount exactly.']);
            }
            $receipts = [];
            foreach ($invoice->lines->whereNotNull('inventory_id')->groupBy('inventory_id') as $itemLines) {
                $firstLine = $itemLines->first();
                $item = Inventory::query()->lockForUpdate()->findOrFail($firstLine->inventory_id);
                if ($item->status !== 'active') {
                    throw ValidationException::withMessages(['lines' => 'Inactive inventory items cannot receive purchase stock.']);
                }
                $quantityHundredths = $itemLines->sum(fn ($line) => $this->quantityHundredths((string) $line->quantity));
                $lineAmount = $itemLines->sum(fn ($line) => (int) $line->line_amount_cents);
                $receiptLines = (object) [
                    'quantity' => $this->decimalFromHundredths($quantityHundredths),
                    'line_amount_cents' => $lineAmount,
                    'description' => $itemLines->pluck('description')->implode('; '),
                ];
                $receipts[] = $this->prepareReceipt($item, $receiptLines, $date, $invoice->invoice_number, $cutover);
            }
            $journalLines[] = ['account' => $ap, 'debit' => 0, 'credit' => $debits];
            $journal = AccountingJournal::query()->create([
                'book_key' => 'FCDC', 'reference' => 'PUR-'.$invoice->id, 'source_type' => 'supplier_purchase',
                'source_id' => (string) $invoice->id, 'accounting_date' => $date, 'posting_period_id' => $period->id,
                'description' => $invoice->description, 'status' => 'draft', 'prepared_by' => $invoice->prepared_by,
            ]);
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
            foreach ($receipts as $receipt) {
                StockMovement::query()->create([
                    'inventory_id' => $receipt['item']->id, 'posted_by' => $actorId, 'type' => 'purchase_receipt',
                    'quantity' => $receipt['quantity'], 'reason_category' => 'supplier_purchase',
                    'notes' => $receipt['description'], 'reference' => $invoice->invoice_number, 'effective_date' => $date,
                    'posted_at' => now('UTC'), 'value_cents' => $receipt['value_cents'],
                    'carrying_value_after_cents' => $receipt['carrying_value_after_cents'],
                    'accounting_journal_id' => $journal->id, 'source_reference' => 'supplier_purchase:'.$invoice->id,
                ]);
                $nextQuantityHundredths = $this->quantityHundredths((string) $receipt['item']->qty)
                    + $this->quantityHundredths((string) $receipt['quantity']);
                Inventory::query()->whereKey($receipt['item']->id)->update([
                    'qty' => $this->decimalFromHundredths($nextQuantityHundredths),
                    'unit_cost' => $nextQuantityHundredths === 0
                        ? '0.00'
                        : number_format($receipt['carrying_value_after_cents'] / $nextQuantityHundredths, 2, '.', ''),
                    'carrying_value_cents' => $receipt['carrying_value_after_cents'],
                ]);
            }
            $invoice->forceFill([
                'status' => 'posted', 'accounting_journal_id' => $journal->id,
                'posted_by' => $actorId, 'posted_at' => now('UTC'),
            ])->save();

            return $invoice->refresh()->load(['lines.inventory', 'lines.account', 'journal']);
        });
    }

    private function prepareReceipt(Inventory $item, object $line, string $date, string $invoiceNumber, string $cutoverDate): array
    {
        $quantity = $this->quantityHundredths((string) $line->quantity);
        if (StockMovement::query()->where('inventory_id', $item->id)->where('reference', $invoiceNumber)->exists()) {
            throw ValidationException::withMessages(['lines' => 'A standalone stock receipt already references this supplier invoice. Use the invoice receipt workflow instead.']);
        }
        $latest = StockMovement::query()->where('inventory_id', $item->id)->whereNotNull('value_cents')
            ->orderByDesc('effective_date')->orderByDesc('posted_at')->orderByDesc('id')->first();
        if ($latest && $latest->effective_date->toDateString() > $date) {
            throw ValidationException::withMessages(['lines' => 'A valued purchase receipt cannot precede the inventory item’s latest valued movement.']);
        }
        $opening = OpeningInventoryValuation::query()->where('book_key', 'FCDC')->where('status', 'approved')->with('lines')->first();
        $openingLine = $opening?->lines->firstWhere('inventory_id', $item->id);
        $currentQuantity = $this->quantityHundredths((string) $item->qty);
        if (! $latest && ! $openingLine && ($item->created_at->timezone('Asia/Manila')->toDateString() < $cutoverDate || $currentQuantity !== 0)) {
            throw ValidationException::withMessages(['lines' => 'An approved opening inventory value is required before recording a valued purchase receipt.']);
        }
        if (! $latest && $openingLine && $currentQuantity !== $this->quantityHundredths((string) $openingLine->quantity)) {
            throw ValidationException::withMessages(['lines' => 'The valued stock schedule does not match the inventory quantity.']);
        }
        if (StockMovement::query()->where('inventory_id', $item->id)->whereNull('value_cents')
            ->where('type', '!=', 'opening_balance')->whereDate('effective_date', '>', $cutoverDate)->exists()) {
            throw ValidationException::withMessages(['lines' => 'An unvalued stock movement after cutover prevents a reliable moving-average receipt.']);
        }
        if ($latest && $latest->carrying_value_after_cents === null) {
            throw ValidationException::withMessages(['lines' => 'The latest valued stock movement has no closing carrying value.']);
        }
        $currentValue = $latest ? (int) $latest->carrying_value_after_cents : (int) ($openingLine?->carrying_value_cents ?? 0);
        $after = $currentValue + (int) $line->line_amount_cents;
        if ($after > PHP_INT_MAX || $currentQuantity > PHP_INT_MAX - $quantity) {
            throw ValidationException::withMessages(['lines' => 'Inventory receipt exceeds the supported quantity or carrying value.']);
        }

        return [
            'item' => $item, 'quantity' => $this->decimalFromHundredths($quantity),
            'value_cents' => (int) $line->line_amount_cents, 'carrying_value_after_cents' => $after,
            'description' => $line->description,
        ];
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
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', trim($amount))) {
            throw ValidationException::withMessages(['amount' => 'Amounts must be positive PHP values with at most two decimal places.']);
        }
        [$whole, $fraction] = array_pad(explode('.', trim($amount), 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        $maximumWhole = (string) intdiv(PHP_INT_MAX, 100);
        if (strlen($whole) > strlen($maximumWhole)
            || (strlen($whole) === strlen($maximumWhole) && strcmp($whole, $maximumWhole) > 0)
            || ($whole === $maximumWhole && (int) str_pad($fraction, 2, '0') > PHP_INT_MAX % 100)) {
            throw ValidationException::withMessages(['amount' => 'Amount exceeds the supported PHP-centavo range.']);
        }

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function quantityHundredths(string $quantity): int
    {
        $quantity = trim($quantity);
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $quantity)) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be positive with at most two decimal places.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        $fraction = str_pad($fraction, 2, '0');
        $maximumWhole = (string) intdiv(PHP_INT_MAX, 100);
        if (strlen($whole) > strlen($maximumWhole)
            || (strlen($whole) === strlen($maximumWhole) && strcmp($whole, $maximumWhole) > 0)
            || ($whole === $maximumWhole && (int) $fraction > PHP_INT_MAX % 100)) {
            throw ValidationException::withMessages(['quantity' => 'Quantity exceeds the supported inventory range.']);
        }

        return ((int) $whole * 100) + (int) $fraction;
    }

    private function decimalFromHundredths(int $quantity): string
    {
        return intdiv($quantity, 100).'.'.str_pad((string) ($quantity % 100), 2, '0', STR_PAD_LEFT);
    }
}
