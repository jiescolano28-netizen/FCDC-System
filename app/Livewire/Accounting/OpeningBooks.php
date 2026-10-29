<?php

namespace App\Livewire\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingYtdSummary;
use App\Models\Inventory;
use App\Models\OpeningInventoryValuation;
use App\Models\Supplier;
use App\Services\Accounting\InterveningSourceReconciliation;
use App\Services\Accounting\OpeningBooksService;
use App\Services\Accounting\SupplierPayablesService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class OpeningBooks extends Component
{
    use WithFileUploads;

    public string $cutoverDate = '';

    public array $lines = [];

    public ?TemporaryUploadedFile $openingCsv = null;

    public string $inventoryEvidence = '';

    public array $inventoryLines = [];

    public ?TemporaryUploadedFile $inventoryCsv = null;

    public ?TemporaryUploadedFile $supplierCsv = null;

    public bool $editingInventoryValuation = false;

    public string $ytdThroughDate = '';

    public string $ytdEvidence = '';

    public array $ytdLines = [];

    public function mount(): void
    {
        $journal = AccountingJournal::where('source_type', 'opening')->where('source_id', 'FCDC')->first();
        $this->cutoverDate = $journal?->accounting_date?->format('Y-m-d') ?? now('Asia/Manila')->toDateString();
        $this->lines = $journal
            ? $journal->lines->map(fn ($line) => [
                'accountId' => (string) $line->accounting_account_id,
                'debit' => $line->debit_cents ? number_format($line->debit_cents / 100, 2, '.', '') : '',
                'credit' => $line->credit_cents ? number_format($line->credit_cents / 100, 2, '.', '') : '',
            ])->all()
            : [['accountId' => '', 'debit' => '', 'credit' => ''], ['accountId' => '', 'debit' => '', 'credit' => '']];
        $cutoverYear = (int) substr($this->cutoverDate, 0, 4);
        $summary = AccountingYtdSummary::where('book_key', 'FCDC')->where('fiscal_year', $cutoverYear)->first();
        $this->ytdThroughDate = $summary?->through_date?->format('Y-m-d') ?? '';
        $this->ytdEvidence = $summary?->evidence_reference ?? '';
        $this->ytdLines = $summary?->lines->map(fn ($line) => [
            'accountId' => (string) $line->accounting_account_id,
            'amount' => number_format($line->amount_cents / 100, 2, '.', ''),
        ])->all() ?? [['accountId' => '', 'amount' => '']];
        $valuation = OpeningInventoryValuation::where('book_key', 'FCDC')->with('lines')->first();
        $this->inventoryEvidence = $valuation?->evidence_reference ?? '';
        $valuationLines = $valuation?->lines->keyBy('inventory_id') ?? collect();
        $this->inventoryLines = Inventory::orderBy('id')->get()->map(function ($item) use ($valuationLines) {
            $line = $valuationLines->get($item->id);

            return [
                'inventoryId' => (string) $item->id,
                'quantity' => $line?->quantity ?? $item->qty,
                'value' => $line ? number_format($line->carrying_value_cents / 100, 2, '.', '') : '',
            ];
        })->all();
        $this->editingInventoryValuation = $valuation?->status !== 'approved';
    }

    public function addOpeningLine(): void
    {
        $this->authorizePermission('accounting.maintain-opening-books');
        $this->lines[] = ['accountId' => '', 'debit' => '', 'credit' => ''];
    }

    public function importOpeningCsv(): void
    {
        $this->authorizePermission('accounting.maintain-opening-books');
        $this->validate(['openingCsv' => 'required|file|mimes:csv,txt|max:2048']);

        $handle = fopen($this->openingCsv->getRealPath(), 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['openingCsv' => 'We could not read that file. Please save it as a CSV and try again.']);
        }

        try {
            $header = fgetcsv($handle);
            $header = is_array($header) ? array_map(fn ($column) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $column))), $header) : [];
            if ($header !== ['account_code', 'debit', 'credit']) {
                throw ValidationException::withMessages(['openingCsv' => 'Use the template columns in this order: account_code, debit, credit.']);
            }

            $accounts = AccountingAccount::where('is_active', true)->get()->keyBy('code');
            $imported = [];
            $seen = [];
            $rowNumber = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                if ($row === [null] || (count($row) === 1 && trim((string) $row[0]) === '')) {
                    continue;
                }
                if (count($row) !== 3) {
                    throw ValidationException::withMessages(['openingCsv' => "Row {$rowNumber} must contain an account code, debit, and credit."]);
                }
                [$code, $debit, $credit] = array_map(fn ($value) => trim((string) $value), $row);
                $account = $accounts->get($code);
                if (! $account?->isApprovedForPosting()) {
                    throw ValidationException::withMessages(['openingCsv' => "Row {$rowNumber}: account code {$code} is not active and approved. Check the Chart of Accounts."]);
                }
                if (isset($seen[$account->id])) {
                    throw ValidationException::withMessages(['openingCsv' => "Row {$rowNumber}: account {$code} appears more than once. Combine its balance into one row."]);
                }
                $seen[$account->id] = true;
                $imported[] = ['accountId' => (string) $account->id, 'debit' => $debit, 'credit' => $credit];
            }
        } finally {
            fclose($handle);
        }

        if (count($imported) < 2) {
            throw ValidationException::withMessages(['openingCsv' => 'The CSV needs at least two account balance rows.']);
        }

        // Import only fills the draft form. The existing service validates and
        // persists balances when the preparer explicitly saves them.
        $this->lines = $imported;
        $this->reset('openingCsv');
        session()->flash('opening-message', count($imported).' account rows imported to the draft. Review the totals, then save.');
    }

    public function saveOpening(): void
    {
        $this->authorizePermission('accounting.maintain-opening-books');
        app(OpeningBooksService::class)->saveOpening($this->cutoverDate, $this->lines, auth()->id());
        session()->flash('opening-message', 'Opening journal saved. Pending approval.');
    }

    public function approveOpening(): void
    {
        $this->authorizePermission('accounting.approve-opening-books');
        app(OpeningBooksService::class)->approveOpening(auth()->id());
        session()->flash('opening-message', 'Opening journal approved.');
    }

    public function approveCutover(): void
    {
        $this->authorizePermission('accounting.approve-opening-books');
        app(OpeningBooksService::class)->approveCutover(auth()->id());
        session()->flash('opening-message', 'Cutover approved and opening balances posted.');
    }

    public function saveInventoryValuation(): void
    {
        $this->authorizePermission('accounting.maintain-opening-books');
        app(OpeningBooksService::class)->saveInventoryValuation(
            $this->cutoverDate,
            $this->inventoryEvidence,
            $this->inventoryLines,
            auth()->id(),
        );
        session()->flash('opening-message', 'Opening inventory schedule saved. Pending approval.');
        $this->editingInventoryValuation = false;
    }

    public function importInventoryCsv(): void
    {
        $this->authorizePermission('accounting.maintain-opening-books');
        $this->validate(['inventoryCsv' => 'required|file|mimes:csv,txt|max:2048']);

        $handle = fopen($this->inventoryCsv->getRealPath(), 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['inventoryCsv' => 'We could not read that file. Please save it as a CSV and try again.']);
        }
        try {
            $header = fgetcsv($handle);
            $header = is_array($header) ? array_map(fn ($column) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $column))), $header) : [];
            if ($header !== ['item_code', 'quantity', 'value']) {
                throw ValidationException::withMessages(['inventoryCsv' => 'Use the template columns in this order: item_code, quantity, value.']);
            }
            $items = Inventory::orderBy('id')->get()->keyBy('code');
            $imported = [];
            $seen = [];
            $rowNumber = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                if ($row === [null] || (count($row) === 1 && trim((string) $row[0]) === '')) {
                    continue;
                }
                if (count($row) !== 3) {
                    throw ValidationException::withMessages(['inventoryCsv' => "Row {$rowNumber} must contain an item code, quantity, and value."]);
                }
                [$code, $quantity, $value] = array_map(fn ($entry) => trim((string) $entry), $row);
                $item = $items->get($code);
                if (! $item) {
                    throw ValidationException::withMessages(['inventoryCsv' => "Row {$rowNumber}: item code {$code} was not found in Inventory."]);
                }
                if (isset($seen[$item->id])) {
                    throw ValidationException::withMessages(['inventoryCsv' => "Row {$rowNumber}: item {$code} appears more than once. Use one row per item."]);
                }
                $seen[$item->id] = true;
                $imported[] = ['inventoryId' => (string) $item->id, 'quantity' => $quantity, 'value' => $value];
            }
        } finally {
            fclose($handle);
        }
        if (count($imported) !== $items->count()) {
            throw ValidationException::withMessages(['inventoryCsv' => 'Include every inventory item exactly once. Download the template again if the item list changed.']);
        }

        $this->inventoryLines = $imported;
        $this->reset('inventoryCsv');
        session()->flash('opening-message', count($imported).' inventory rows imported to the draft. Add the count/value evidence reference and save.');
    }

    public function importSupplierCsv(): void
    {
        $this->authorizePermission('accounting.maintain-opening-books');
        $this->authorizePermission('accounting.maintain-suppliers');
        $this->validate(['supplierCsv' => 'required|file|mimes:csv,txt|max:2048']);

        $handle = fopen($this->supplierCsv->getRealPath(), 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['supplierCsv' => 'We could not read that file. Please save it as a CSV and try again.']);
        }
        $rows = [];
        try {
            $header = fgetcsv($handle);
            $expectedHeader = ['supplier_code', 'invoice_number', 'recognition_date', 'due_date', 'amount', 'description', 'terms'];
            $header = is_array($header) ? array_map(fn ($column) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $column))), $header) : [];
            if ($header !== $expectedHeader) {
                throw ValidationException::withMessages(['supplierCsv' => 'Use the template columns in this order: '.implode(',', $expectedHeader).'.']);
            }
            $rowNumber = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                if ($row === [null] || (count($row) === 1 && trim((string) $row[0]) === '')) {
                    continue;
                }
                if (count($row) !== count($expectedHeader)) {
                    throw ValidationException::withMessages(['supplierCsv' => "Row {$rowNumber} must contain all seven supplier invoice fields."]);
                }
                $rows[] = array_combine($expectedHeader, array_map(fn ($value) => trim((string) $value), $row));
            }
        } finally {
            fclose($handle);
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['supplierCsv' => 'The supplier CSV has no invoice rows to import.']);
        }

        $suppliers = Supplier::query()->get()->keyBy(fn ($supplier) => mb_strtoupper($supplier->code, 'UTF-8'));
        try {
            DB::transaction(function () use ($rows, $suppliers): void {
                foreach ($rows as $index => $row) {
                    $supplier = $suppliers->get(mb_strtoupper($row['supplier_code'], 'UTF-8'));
                    if (! $supplier) {
                        throw ValidationException::withMessages(['supplierCsv' => 'Row '.($index + 2).": supplier code {$row['supplier_code']} was not found. Add the supplier first in Accounts Payable."]);
                    }
                    app(SupplierPayablesService::class)->saveOpeningInvoice([
                        'supplier_id' => $supplier->id,
                        'invoice_number' => $row['invoice_number'],
                        'recognition_date' => $row['recognition_date'],
                        'due_date' => $row['due_date'],
                        'amount' => $row['amount'],
                        'description' => $row['description'],
                        'terms' => $row['terms'],
                    ], (int) auth()->id());
                }
            });
        } catch (ValidationException $exception) {
            $messages = collect($exception->errors())->flatten()->all();
            throw ValidationException::withMessages(['supplierCsv' => $messages]);
        }

        $this->reset('supplierCsv');
        session()->flash('opening-message', count($rows).' supplier invoices imported as drafts. Their total must match the Accounts Payable opening balance.');
    }

    public function approveInventoryValuation(): void
    {
        $this->authorizePermission('accounting.approve-opening-books');
        app(OpeningBooksService::class)->approveInventoryValuation(auth()->id());
        session()->flash('opening-message', 'Opening valuation approved.');
        $this->editingInventoryValuation = false;
    }

    public function reviseInventoryValuation(): void
    {
        $this->authorizePermission('accounting.maintain-opening-books');
        abort_if(OpeningInventoryValuation::openingJournalPosted(), 409);
        $this->editingInventoryValuation = true;
    }

    public function addYtdLine(): void
    {
        $this->authorizePermission('accounting.maintain-opening-books');
        $this->ytdLines[] = ['accountId' => '', 'amount' => ''];
    }

    public function saveYtdSummary(): void
    {
        $this->authorizePermission('accounting.maintain-opening-books');
        $date = $this->cutoverDate;
        $year = (int) substr($date, 0, 4);
        app(OpeningBooksService::class)->saveYtdSummary($year, $this->ytdThroughDate, $this->ytdEvidence, $this->ytdLines, auth()->id());
        session()->flash('opening-message', 'YTD opening summary saved. Pending approval.');
    }

    public function approveYtdSummary(): void
    {
        $this->authorizePermission('accounting.approve-opening-books');
        app(OpeningBooksService::class)->approveYtdSummary(auth()->id());
        session()->flash('opening-message', 'Pre-cutover YTD summary approved.');
    }

    public function includeInterveningSource(string $type, int $sourceId): void
    {
        $this->authorizePermission('accounting.reconcile-sources');
        $this->authorizePermission('accounting.post-reconciled-sources');
        $reconciliation = app(InterveningSourceReconciliation::class);
        match ($type) {
            'pos_sale' => $reconciliation->includePosSale($sourceId, auth()->id()),
            'stock_movement' => $reconciliation->includeStockMovement($sourceId, auth()->id()),
            'supplier_purchase' => $reconciliation->includeSupplierPurchase($sourceId, auth()->id()),
            'cash_disbursement' => $reconciliation->includePayment($sourceId, auth()->id()),
            default => abort(422, 'This source type cannot be included from this review.'),
        };
        session()->flash('opening-message', 'Existing source included without creating another operational record.');
    }

    public function activateProduction(): void
    {
        $this->authorizePermission('accounting.activate-books');
        app(OpeningBooksService::class)->activateProduction((int) auth()->id());
        session()->flash('opening-message', 'Production accounting activated.');
    }

    public function render()
    {
        $journal = AccountingJournal::where('source_type', 'opening')->where('source_id', 'FCDC')->with('lines.account', 'approver')->first();
        $sources = app(InterveningSourceReconciliation::class)->sources();
        [$debitTotalCents, $creditTotalCents] = $this->openingTotals();

        return view('livewire.accounting.opening-books', [
            'accounts' => AccountingAccount::where('is_active', true)->orderBy('code')->get(),
            'journal' => $journal,
            'inventoryItems' => Inventory::orderBy('id')->get(),
            'inventoryValuation' => OpeningInventoryValuation::where('book_key', 'FCDC')->with('lines.inventory', 'approver')->first(),
            'ytdSummary' => AccountingYtdSummary::where('book_key', 'FCDC')->where('fiscal_year', (int) substr($this->cutoverDate, 0, 4))->with('lines.account', 'approver')->first(),
            'interveningSources' => $sources,
            'readiness' => app(OpeningBooksService::class)->readiness(),
            'debitTotalCents' => $debitTotalCents,
            'creditTotalCents' => $creditTotalCents,
            'openingBalanced' => $debitTotalCents !== null && $creditTotalCents !== null && $debitTotalCents > 0 && $debitTotalCents === $creditTotalCents,
        ])->layout('layouts.app', ['title' => 'Opening Books & Cutover']);
    }

    /** @return array{0: ?int, 1: ?int} */
    private function openingTotals(): array
    {
        $debits = 0;
        $credits = 0;
        foreach ($this->lines as $line) {
            $debit = $this->amountInCents($line['debit'] ?? '');
            $credit = $this->amountInCents($line['credit'] ?? '');
            if ($debit === null || $credit === null || $debit > PHP_INT_MAX - $debits || $credit > PHP_INT_MAX - $credits) {
                return [null, null];
            }
            $debits += $debit;
            $credits += $credit;
        }

        return [$debits, $credits];
    }

    private function amountInCents(mixed $amount): ?int
    {
        if ($amount === '' || $amount === null) {
            return 0;
        }
        if (! is_string($amount) && ! is_int($amount)) {
            return null;
        }
        $amount = trim((string) $amount);
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.(\d{1,2}))?$/D', $amount)) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $cents = ltrim($whole.str_pad($fraction, 2, '0'), '0') ?: '0';
        if (strlen($cents) > strlen((string) PHP_INT_MAX)
            || (strlen($cents) === strlen((string) PHP_INT_MAX) && strcmp($cents, (string) PHP_INT_MAX) > 0)) {
            return null;
        }

        return (int) $cents;
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
