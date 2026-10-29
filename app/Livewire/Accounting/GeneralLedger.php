<?php

namespace App\Livewire\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingJournalLine;
use App\Models\AccountingPostingPeriod;
use App\Models\CashDisbursement;
use App\Models\OperatingCostRecord;
use App\Models\PosTransaction;
use App\Models\ReworkingCostRecord;
use App\Models\StockMovement;
use App\Models\SupplierPurchaseCorrection;
use App\Models\SupplierPurchaseInvoice;
use App\Services\Accounting\OpeningBooksService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;

class GeneralLedger extends Component
{
    #[Url]
    public string $bookKey = 'FCDC';

    #[Url]
    public string $accountId = '';

    #[Url]
    public string $cutoffDate = '';

    #[Url]
    public string $toDate = '';

    /**
     * Backward-compatible alias for the cut-off date used by existing links.
     */
    public string $fromDate = '';

    public function mount(): void
    {
        $today = CarbonImmutable::now('Asia/Manila');
        $this->toDate = $this->toDate !== '' ? $this->toDate : $today->toDateString();

        if ($this->fromDate === '') {
            $legacyFromDate = request()->query('fromDate');
            if (is_string($legacyFromDate)) {
                $this->fromDate = $legacyFromDate;
            }
        }
        if ($this->cutoffDate === '' && $this->fromDate !== '') {
            $this->cutoffDate = $this->fromDate;
        }
        $this->fromDate = $this->cutoffDate;
    }

    public function updatedFromDate(string $value): void
    {
        if ($this->cutoffDate !== $value) {
            $this->cutoffDate = $value;
        }
    }

    public function updatedCutoffDate(string $value): void
    {
        if ($this->fromDate !== $value) {
            $this->fromDate = $value;
        }
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('accounting.view'), 403);

        $books = $this->bookOptions();
        if (! $books->contains($this->bookKey)) {
            $this->bookKey = (string) $books->first();
        }

        $cutoverJournal = AccountingJournal::query()
            ->where('book_key', $this->bookKey)
            ->where('source_type', 'opening')
            ->where('status', 'posted')
            ->with('approver')
            ->orderBy('accounting_date')
            ->first();
        $cutover = $cutoverJournal?->accounting_date;
        $cutoverDate = $cutover ? CarbonImmutable::parse($cutover, 'Asia/Manila')->toDateString() : null;
        $openingBalancesIncomplete = $cutoverJournal === null;
        $account = $this->accountId !== ''
            ? AccountingAccount::query()->whereNotNull('approved_at')->find($this->accountId)
            : null;
        $dateError = $this->dateRangeError($cutoverDate);
        $reportStartDate = $this->reportStartDate($cutoverDate);
        $lines = collect();
        $allAccountRows = collect();
        $openingBalanceCents = 0;
        $totalDebitCents = 0;
        $totalCreditCents = 0;
        $closingBalanceCents = 0;

        if ($dateError === null) {
            $history = AccountingJournalLine::query()
                ->with(['journal.correctionOf', 'account'])
                ->when($account, fn ($query) => $query->where('accounting_account_id', $account->id))
                ->whereHas('journal', fn (Builder $query) => $query
                    ->where('book_key', $this->bookKey)
                    ->where('status', 'posted')
                    ->when($cutoverDate !== null, fn (Builder $query) => $query->whereDate('accounting_date', '>=', $cutoverDate))
                    ->whereDate('accounting_date', '<=', $this->toDate))
                ->join('accounting_journals', 'accounting_journal_lines.accounting_journal_id', '=', 'accounting_journals.id')
                ->orderBy('accounting_journals.accounting_date')
                ->orderBy('accounting_journals.id')
                ->orderBy('accounting_journal_lines.id')
                ->select('accounting_journal_lines.*')
                ->get();

            $allAccountRows = $history->groupBy('accounting_account_id')->map(function ($accountLines) use ($reportStartDate): array {
                $openingIsOpeningBalance = $this->cutoffDate === '' && $this->fromDate === '';
                $isOpeningLine = fn (AccountingJournalLine $line) => $line->journal->source_type === 'opening'
                    && ($line->journal->source_id === 'FCDC' || $openingIsOpeningBalance);
                $openingLines = $accountLines->filter(fn (AccountingJournalLine $line) => $isOpeningLine($line)
                    || $line->journal->accounting_date->toDateString() < $reportStartDate);
                $openingBalance = $this->signedBalance($openingLines);
                $periodLines = $accountLines->filter(fn (AccountingJournalLine $line) => ! $isOpeningLine($line)
                    && $line->journal->accounting_date->toDateString() >= $reportStartDate);
                $runningBalance = $openingBalance;
                $periodRows = $periodLines
                    ->map(function (AccountingJournalLine $line) use (&$runningBalance): array {
                        $runningBalance += (int) $line->debit_cents - (int) $line->credit_cents;

                        return ['journal' => $line->journal, 'account' => $line->account, 'description' => $line->description,
                            'debit_cents' => (int) $line->debit_cents, 'credit_cents' => (int) $line->credit_cents, 'balance_cents' => $runningBalance];
                    })->values();

                return [
                    'account' => $accountLines->first()->account,
                    'opening' => $openingBalance,
                    'total_debit_cents' => (int) $periodLines->sum('debit_cents'),
                    'total_credit_cents' => (int) $periodLines->sum('credit_cents'),
                    'closing' => $runningBalance,
                    'lines' => $periodRows,
                ];
            })->sortBy(fn (array $row) => $row['account']->code, SORT_NATURAL)->values();

            if ($account) {
                $accountRow = $allAccountRows->first(fn (array $row) => $row['account']->id === $account->id);
                if ($accountRow) {
                    $openingBalanceCents = $accountRow['opening'];
                    $totalDebitCents = $accountRow['total_debit_cents'];
                    $totalCreditCents = $accountRow['total_credit_cents'];
                    $closingBalanceCents = $accountRow['closing'];
                    $lines = $accountRow['lines'];
                }
            }
        }

        $accounts = AccountingAccount::query()
            ->whereNotNull('approved_at')
            ->where(fn (Builder $query) => $query->where('is_active', true)
                ->orWhereIn('id', AccountingJournalLine::query()
                    ->whereHas('journal', fn (Builder $journals) => $journals->where('book_key', $this->bookKey)->where('status', 'posted'))
                    ->select('accounting_account_id')))
            ->orderBy('code')
            ->get();

        $reportDebitCents = $account ? $totalDebitCents : (int) $allAccountRows->sum('total_debit_cents');
        $reportCreditCents = $account ? $totalCreditCents : (int) $allAccountRows->sum('total_credit_cents');
        $reportOpeningCents = $account ? $openingBalanceCents : (int) $allAccountRows->sum('opening');
        $reportClosingCents = $account ? $closingBalanceCents : (int) $allAccountRows->sum('closing');
        $productionActivated = (bool) app(OpeningBooksService::class)->readiness()['production_activated'];

        return view('livewire.accounting.general-ledger', [
            'books' => $books,
            'bookKey' => $this->bookKey,
            'bookLabel' => $this->bookLabel($this->bookKey),
            'accounts' => $accounts,
            'selectedAccount' => $account,
            'cutoverDate' => $cutoverDate,
            'cutoverJournal' => $cutoverJournal,
            'openingBalancesIncomplete' => $openingBalancesIncomplete,
            'reportStartDate' => $reportStartDate,
            'dateError' => $dateError,
            'openingBalanceCents' => $openingBalanceCents,
            'totalDebitCents' => $totalDebitCents,
            'totalCreditCents' => $totalCreditCents,
            'closingBalanceCents' => $closingBalanceCents,
            'reportDebitCents' => $reportDebitCents,
            'reportCreditCents' => $reportCreditCents,
            'reportOpeningCents' => $reportOpeningCents,
            'reportClosingCents' => $reportClosingCents,
            'productionActivated' => $productionActivated,
            'lines' => $lines,
            'allAccountRows' => $allAccountRows,
            'trialBalanceUrl' => $this->bookKey === 'FCDC' && $reportStartDate && $dateError === null
                ? route('accounting.trial-balance', ['fromDate' => $reportStartDate, 'toDate' => $this->toDate])
                : null,
        ])->layout('layouts.app', ['title' => 'General Ledger']);
    }

    private function bookOptions(): Collection
    {
        $books = AccountingJournal::query()
            ->select('book_key')->distinct()->pluck('book_key')
            ->merge(AccountingPostingPeriod::query()->select('book_key')->distinct()->pluck('book_key'))
            ->filter(fn ($book) => is_string($book) && trim($book) !== '')
            ->map(fn (string $book) => trim($book))
            ->push('FCDC')
            ->unique()
            ->sort()
            ->values();

        return $books->isEmpty() ? collect(['FCDC']) : $books;
    }

    public function bookLabel(string $bookKey): string
    {
        if ($bookKey === 'FCDC') {
            return 'FCDC General Book';
        }

        $label = Str::of($bookKey)->replace(['_', '-'], ' ')->squish()->title()->toString();

        return Str::contains(Str::lower($label), 'book') ? $label : $label.' Book';
    }

    private function reportStartDate(?string $cutoverDate): ?string
    {
        if ($this->cutoffDate !== '') {
            return $this->cutoffDate;
        }
        if ($this->fromDate !== '') {
            return $this->fromDate;
        }
        if ($cutoverDate !== null) {
            return $cutoverDate;
        }

        return $this->earliestPostedDate() ?? $this->toDate;
    }

    private function earliestPostedDate(): ?string
    {
        if (! CarbonImmutable::hasFormat($this->toDate, 'Y-m-d')) {
            return null;
        }

        $date = AccountingJournal::query()
            ->where('book_key', $this->bookKey)
            ->where('status', 'posted')
            ->whereDate('accounting_date', '<=', $this->toDate)
            ->min('accounting_date');

        return $date ? CarbonImmutable::parse($date, 'Asia/Manila')->toDateString() : null;
    }

    private function dateRangeError(?string $cutoverDate): ?string
    {
        $fromDate = $this->reportStartDate($cutoverDate);
        if ($fromDate === null
            || ! CarbonImmutable::hasFormat($fromDate, 'Y-m-d')
            || ! CarbonImmutable::hasFormat($this->toDate, 'Y-m-d')) {
            return 'Choose a valid cut-off and end date.';
        }

        $from = CarbonImmutable::createFromFormat('!Y-m-d', $fromDate, 'Asia/Manila');
        $to = CarbonImmutable::createFromFormat('!Y-m-d', $this->toDate, 'Asia/Manila');
        if (! $from || ! $to || $from->format('Y-m-d') !== $fromDate || $to->format('Y-m-d') !== $this->toDate || $from->gt($to)) {
            return 'Choose a valid inclusive date range.';
        }
        if ($cutoverDate !== null && $fromDate < $cutoverDate) {
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
            'opening' => $journal->source_id === 'FCDC' ? route('accounting.journal-entry', ['journal' => $journal->id]) : null,
            'supplier_purchase', 'supplier_purchase_correction', 'supplier_purchase_vat_reclassification' => SupplierPurchaseInvoice::query()->whereKey($journal->source_id)->exists()
                ? route('accounting.supplier-purchases', ['invoice' => $journal->source_id]) : null,
            'cash_disbursement', 'cash_disbursement_reversal', 'direct_purchase_vat_reclassification' => CashDisbursement::query()->whereKey($journal->source_id)->exists()
                ? route('accounting.cash-disbursements', ['disbursement' => $journal->source_id]) : null,
            'direct_purchase_correction' => $this->directCorrectionUrl($journal),
            'supplier_refund_receipt' => $this->refundCorrectionUrl($journal),
            'pos_sale' => auth()->user()?->can('pos.view') && PosTransaction::query()->whereKey($journal->source_id)->where('status', 'completed')->exists()
                ? route('pos', ['receipt' => $journal->source_id]) : null,
            'reworking_cost' => auth()->user()?->can('inventory.view') && ReworkingCostRecord::query()->whereKey($journal->source_id)->exists()
                ? route('inventory.reworking-cost-records') : null,
            'operating_cost' => auth()->user()?->can('inventory.view') && OperatingCostRecord::query()->whereKey($journal->source_id)->exists()
                ? route('inventory.operating-cost-records') : null,
            'stock_movement', 'stock_valuation_correction', 'recovered_material_correction' => auth()->user()?->can('inventory.view') && StockMovement::query()->whereKey($journal->source_id)->exists()
                ? $this->stockHistoryUrl($journal->source_id) : null,
            default => null,
        };
    }

    private function directCorrectionUrl(AccountingJournal $journal): ?string
    {
        $disbursementId = SupplierPurchaseCorrection::query()->where('journal_id', $journal->id)->value('cash_disbursement_id');

        return $disbursementId ? route('accounting.cash-disbursements', ['disbursement' => $disbursementId]) : null;
    }

    private function refundCorrectionUrl(AccountingJournal $journal): ?string
    {
        $correction = AccountingJournal::query()->whereKey($journal->correction_of_id)->first();
        if (! $correction) {
            return null;
        }
        if ($correction->source_type === 'supplier_purchase_correction'
            && SupplierPurchaseInvoice::query()->whereKey($correction->source_id)->exists()) {
            return route('accounting.supplier-purchases', ['invoice' => $correction->source_id]);
        }
        if ($correction->source_type === 'direct_purchase_correction') {
            return $this->directCorrectionUrl($correction);
        }

        return null;
    }

    private function stockHistoryUrl(string $movementId): string
    {
        $itemId = StockMovement::query()->whereKey($movementId)->value('inventory_id');

        return route('inventory.management', ['item' => $itemId]);
    }
}
