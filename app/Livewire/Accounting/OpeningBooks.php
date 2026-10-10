<?php

namespace App\Livewire\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingYtdSummary;
use App\Models\Inventory;
use App\Models\OpeningInventoryValuation;
use App\Services\Accounting\InterveningSourceReconciliation;
use App\Services\Accounting\OpeningBooksService;
use Livewire\Component;

class OpeningBooks extends Component
{
    public string $cutoverDate = '';

    public array $lines = [];

    public string $inventoryEvidence = '';

    public array $inventoryLines = [];

    public bool $editingInventoryValuation = false;

    public string $ytdThroughDate = '';

    public string $ytdEvidence = '';

    public array $ytdLines = [];

    public function mount(): void
    {
        $journal = AccountingJournal::where('source_type', 'opening')->where('source_id', 'FCDC')->first();
        $this->cutoverDate = $journal?->accounting_date?->format('Y-m-d') ?? now('Asia/Manila')->toDateString();
        $this->lines = $journal?->status === 'draft'
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

    public function render()
    {
        $journal = AccountingJournal::where('source_type', 'opening')->where('source_id', 'FCDC')->with('lines.account', 'approver')->first();
        $sources = app(InterveningSourceReconciliation::class)->sources();

        return view('livewire.accounting.opening-books', [
            'accounts' => AccountingAccount::where('is_active', true)->orderBy('code')->get(),
            'journal' => $journal,
            'inventoryItems' => Inventory::orderBy('id')->get(),
            'inventoryValuation' => OpeningInventoryValuation::where('book_key', 'FCDC')->with('lines.inventory', 'approver')->first(),
            'ytdSummary' => AccountingYtdSummary::where('book_key', 'FCDC')->where('fiscal_year', (int) substr($this->cutoverDate, 0, 4))->with('lines.account', 'approver')->first(),
            'interveningSources' => $sources,
            'readiness' => app(OpeningBooksService::class)->readiness(),
        ])->layout('layouts.app', ['title' => 'Opening Books & Cutover']);
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
