<?php

namespace App\Livewire\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingJournalLine;
use App\Models\CashDisbursement;
use App\Models\PosTransaction;
use App\Models\StockMovement;
use App\Models\SupplierPurchaseInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class GeneralLedger extends Component
{
    public string $accountId = '';

    public string $fromDate = '';

    public string $toDate = '';

    public function mount(): void
    {
        $this->fromDate = now('Asia/Manila')->startOfMonth()->toDateString();
        $this->toDate = now('Asia/Manila')->toDateString();
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('accounting.view'), 403);

        $cutover = AccountingJournal::query()
            ->where('book_key', 'FCDC')
            ->where('source_type', 'opening')
            ->where('source_id', 'FCDC')
            ->where('status', 'posted')
            ->value('accounting_date');
        $cutoverDate = $cutover ? CarbonImmutable::parse($cutover, 'Asia/Manila')->toDateString() : null;
        $account = $this->accountId !== ''
            ? AccountingAccount::query()->whereNotNull('approved_at')->find($this->accountId)
            : null;
        $dateError = $this->dateRangeError($cutoverDate);
        $lines = collect();
        $openingBalanceCents = 0;
        $closingBalanceCents = 0;

        if ($account && $cutoverDate && $dateError === null) {
            $history = AccountingJournalLine::query()
                ->with(['journal.correctionOf', 'account'])
                ->where('accounting_account_id', $account->id)
                ->whereHas('journal', fn (Builder $query) => $query
                    ->where('book_key', 'FCDC')
                    ->where('status', 'posted')
                    ->whereDate('accounting_date', '<=', $this->toDate))
                ->join('accounting_journals', 'accounting_journal_lines.accounting_journal_id', '=', 'accounting_journals.id')
                ->orderBy('accounting_journals.accounting_date')
                ->orderBy('accounting_journals.id')
                ->orderBy('accounting_journal_lines.id')
                ->select('accounting_journal_lines.*')
                ->get();

            $openingLines = $history->filter(fn (AccountingJournalLine $line) => $line->journal->accounting_date->toDateString() < $this->fromDate);
            $openingBalanceCents = $this->signedBalance($openingLines);
            $balanceCents = $openingBalanceCents;
            $lines = $history->filter(fn (AccountingJournalLine $line) => $line->journal->accounting_date->toDateString() >= $this->fromDate)
                ->map(function (AccountingJournalLine $line) use (&$balanceCents): array {
                    $balanceCents += (int) $line->debit_cents - (int) $line->credit_cents;

                    return [
                        'journal' => $line->journal,
                        'description' => $line->description,
                        'debit_cents' => (int) $line->debit_cents,
                        'credit_cents' => (int) $line->credit_cents,
                        'balance_cents' => $balanceCents,
                    ];
                })->values();
            $closingBalanceCents = $balanceCents;
        }

        $accounts = AccountingAccount::query()
            ->whereNotNull('approved_at')
            ->where(fn (Builder $query) => $query->where('is_active', true)
                ->orWhereIn('id', AccountingJournalLine::query()
                    ->whereHas('journal', fn (Builder $journals) => $journals->where('book_key', 'FCDC')->where('status', 'posted'))
                    ->select('accounting_account_id')))
            ->orderBy('code')
            ->get();

        return view('livewire.accounting.general-ledger', [
            'accounts' => $accounts,
            'selectedAccount' => $account,
            'cutoverDate' => $cutoverDate,
            'dateError' => $dateError,
            'openingBalanceCents' => $openingBalanceCents,
            'closingBalanceCents' => $closingBalanceCents,
            'lines' => $lines,
        ])->layout('layouts.app', ['title' => 'General Ledger']);
    }

    private function dateRangeError(?string $cutoverDate): ?string
    {
        if (! $cutoverDate) {
            return 'Posted General Ledger coverage is unavailable until approved opening balances are posted.';
        }

        if (! CarbonImmutable::hasFormat($this->fromDate, 'Y-m-d') || ! CarbonImmutable::hasFormat($this->toDate, 'Y-m-d')) {
            return 'Choose a valid start and end date.';
        }

        $from = CarbonImmutable::createFromFormat('!Y-m-d', $this->fromDate, 'Asia/Manila');
        $to = CarbonImmutable::createFromFormat('!Y-m-d', $this->toDate, 'Asia/Manila');
        if (! $from || ! $to || $from->format('Y-m-d') !== $this->fromDate || $to->format('Y-m-d') !== $this->toDate || $from->gt($to)) {
            return 'Choose a valid inclusive date range.';
        }
        if ($this->fromDate < $cutoverDate) {
            return 'The selected range begins before approved accounting cutover coverage.';
        }

        return null;
    }

    private function signedBalance(iterable $lines): int
    {
        $balance = 0;
        foreach ($lines as $line) {
            $balance += (int) $line->debit_cents - (int) $line->credit_cents;
        }

        return $balance;
    }

    public function journalUrl(AccountingJournal $journal): string
    {
        return route('accounting.journal-entry', ['journal' => $journal->id]);
    }

    public function sourceUrl(AccountingJournal $journal): ?string
    {
        return match ($journal->source_type) {
            'opening' => $journal->source_id === 'FCDC' ? route('accounting.opening-books') : null,
            'supplier_purchase' => SupplierPurchaseInvoice::query()->whereKey($journal->source_id)->exists()
                ? route('accounting.supplier-purchases', ['invoice' => $journal->source_id]) : null,
            'cash_disbursement', 'cash_disbursement_reversal' => CashDisbursement::query()->whereKey($journal->source_id)->exists()
                ? route('accounting.cash-disbursements', ['disbursement' => $journal->source_id]) : null,
            'pos_sale' => auth()->user()?->can('pos.view') && PosTransaction::query()->whereKey($journal->source_id)->where('status', 'completed')->exists()
                ? route('pos', ['receipt' => $journal->source_id]) : null,
            'stock_movement' => auth()->user()?->can('inventory.view') && StockMovement::query()->whereKey($journal->source_id)->exists()
                ? $this->stockHistoryUrl($journal->source_id) : null,
            default => null,
        };
    }

    private function stockHistoryUrl(string $movementId): string
    {
        $itemId = StockMovement::query()->whereKey($movementId)->value('inventory_id');

        return route('inventory.management', ['item' => $itemId]);
    }
}
