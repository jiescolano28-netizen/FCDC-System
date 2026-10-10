<?php

namespace App\Livewire\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingJournalLine;
use App\Models\OpeningInventoryValuation;
use App\Models\StockMovement;
use App\Models\SupplierOpeningInvoice;
use App\Models\SupplierPurchaseInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class TrialBalance extends Component
{
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
            ->where('book_key', 'FCDC')->where('source_type', 'opening')->where('source_id', 'FCDC')
            ->where('status', 'posted')->value('accounting_date');
        $cutoverDate = $cutover ? CarbonImmutable::parse($cutover, 'Asia/Manila')->toDateString() : null;
        $dateError = $this->dateRangeError($cutoverDate);
        $accounts = collect();
        $totalDebitsCents = 0;
        $totalCreditsCents = 0;
        $periodDebitsCents = 0;
        $periodCreditsCents = 0;
        $apScheduleCents = null;
        $inventoryScheduleCents = null;
        $apBookCents = 0;
        $inventoryBookCents = 0;
        $apMismatch = false;
        $inventoryMismatch = false;
        $inventoryCoverageError = null;

        if ($dateError === null) {
            $ledgerLines = AccountingJournalLine::query()
                ->join('accounting_journals', 'accounting_journals.id', '=', 'accounting_journal_lines.accounting_journal_id')
                ->where('accounting_journals.book_key', 'FCDC')
                ->where('accounting_journals.status', 'posted')
                ->whereDate('accounting_journals.accounting_date', '<=', $this->toDate)
                ->select('accounting_journal_lines.*', 'accounting_journals.accounting_date as posted_date')
                ->get();
            $accountTotals = $ledgerLines->groupBy('accounting_account_id')->map(fn ($lines) => [
                'debit' => (int) $lines->sum('debit_cents'),
                'credit' => (int) $lines->sum('credit_cents'),
                'period_debit' => (int) $lines->filter(fn ($line) => $line->posted_date >= $this->fromDate)->sum('debit_cents'),
                'period_credit' => (int) $lines->filter(fn ($line) => $line->posted_date >= $this->fromDate)->sum('credit_cents'),
            ]);
            $accounts = AccountingAccount::query()
                ->where(fn (Builder $query) => $query
                    ->where(fn (Builder $approvedAccounts) => $approvedAccounts->whereNotNull('approved_at')->where('is_active', true))
                    ->orWhereIn('id', $accountTotals->keys()))
                ->orderBy('code')->get()
                ->map(function (AccountingAccount $account) use ($accountTotals, &$totalDebitsCents, &$totalCreditsCents, &$periodDebitsCents, &$periodCreditsCents) {
                    $totals = $accountTotals->get($account->id, ['debit' => 0, 'credit' => 0, 'period_debit' => 0, 'period_credit' => 0]);
                    $closing = $totals['debit'] - $totals['credit'];
                    $debit = max(0, $closing);
                    $credit = max(0, -$closing);
                    $totalDebitsCents += $debit;
                    $totalCreditsCents += $credit;
                    $periodDebitsCents += $totals['period_debit'];
                    $periodCreditsCents += $totals['period_credit'];

                    return [
                        'account' => $account,
                        'opening_debit_cents' => max(0, ($totals['debit'] - $totals['period_debit']) - ($totals['credit'] - $totals['period_credit'])),
                        'opening_credit_cents' => max(0, -(($totals['debit'] - $totals['period_debit']) - ($totals['credit'] - $totals['period_credit']))),
                        'period_debit_cents' => $totals['period_debit'],
                        'period_credit_cents' => $totals['period_credit'],
                        'closing_debit_cents' => $debit,
                        'closing_credit_cents' => $credit,
                    ];
                })->values();

            $apAccounts = $accounts->filter(fn ($row) => $row['account']->classification === 'accounts_payable');
            $apBookCents = (int) $apAccounts->sum(fn ($row) => $row['closing_credit_cents'] - $row['closing_debit_cents']);
            if ($apAccounts->isNotEmpty()) {
                $apScheduleCents = $this->accountsPayableAsOf($this->toDate);
                $apMismatch = $apBookCents !== $apScheduleCents;
            }

            $inventoryAccounts = $accounts->filter(fn ($row) => $row['account']->classification === 'inventory');
            $inventoryBookCents = (int) $inventoryAccounts->sum(fn ($row) => $row['closing_debit_cents'] - $row['closing_credit_cents']);
            if ($inventoryAccounts->isNotEmpty()) {
                [$inventoryScheduleCents, $inventoryCoverageError] = $this->inventoryValueAsOf($this->toDate, $cutoverDate);
                $inventoryMismatch = $inventoryScheduleCents !== null && $inventoryBookCents !== $inventoryScheduleCents;
            }
        }

        return view('livewire.accounting.trial-balance', [
            'cutoverDate' => $cutoverDate,
            'dateError' => $dateError,
            'accounts' => $accounts,
            'totalDebitsCents' => $totalDebitsCents,
            'totalCreditsCents' => $totalCreditsCents,
            'periodDebitsCents' => $periodDebitsCents,
            'periodCreditsCents' => $periodCreditsCents,
            'isBalanced' => $dateError === null && $totalDebitsCents === $totalCreditsCents,
            'apBookCents' => $apBookCents,
            'apScheduleCents' => $apScheduleCents,
            'apMismatch' => $apMismatch,
            'inventoryBookCents' => $inventoryBookCents,
            'inventoryScheduleCents' => $inventoryScheduleCents,
            'inventoryMismatch' => $inventoryMismatch,
            'inventoryCoverageError' => $inventoryCoverageError,
            'hasActivity' => $periodDebitsCents !== 0 || $periodCreditsCents !== 0,
        ])->layout('layouts.app', ['title' => 'Trial Balance']);
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

    private function accountsPayableAsOf(string $date): int
    {
        $openingInvoices = SupplierOpeningInvoice::query()->where('status', 'posted')->with('openingJournal')
            ->get()->filter(fn (SupplierOpeningInvoice $invoice) => $invoice->openingJournal?->accounting_date?->toDateString() <= $date);
        $purchaseInvoices = SupplierPurchaseInvoice::query()->where('status', 'posted')->whereDate('recognition_date', '<=', $date)->get();
        $invoiceCents = (int) $openingInvoices->sum(fn ($invoice) => $invoice->reversal_of_id ? -(int) $invoice->amount_cents : (int) $invoice->amount_cents)
            + (int) $purchaseInvoices->sum('gross_amount_cents');
        $paymentsCents = (int) DB::table('cash_disbursement_lines')
            ->join('cash_disbursements', 'cash_disbursements.id', '=', 'cash_disbursement_lines.cash_disbursement_id')
            ->where('cash_disbursements.status', 'posted')
            ->whereDate('cash_disbursements.payment_date', '<=', $date)
            ->selectRaw('COALESCE(SUM(CASE WHEN cash_disbursements.reversal_of_id IS NULL THEN cash_disbursement_lines.amount_cents ELSE -cash_disbursement_lines.amount_cents END), 0) as paid_cents')
            ->value('paid_cents');

        return $invoiceCents - $paymentsCents;
    }

    /** @return array{?int, ?string} */
    private function inventoryValueAsOf(string $date, string $cutoverDate): array
    {
        $valuation = OpeningInventoryValuation::query()->where('book_key', 'FCDC')->where('status', 'approved')->with('lines')->first();
        if (! $valuation || $valuation->cutover_date->toDateString() !== $cutoverDate) {
            return [null, 'An approved opening inventory valuation matching accounting cutover is required.'];
        }
        if (StockMovement::query()->whereNull('value_cents')->where('type', '!=', 'opening_balance')
            ->whereDate('effective_date', '>=', $cutoverDate)->whereDate('effective_date', '<=', $date)->exists()) {
            return [null, 'An unvalued stock movement prevents reliable inventory schedule reconciliation.'];
        }

        $values = $valuation->lines->mapWithKeys(fn ($line) => [$line->inventory_id => (int) $line->carrying_value_cents]);
        $latestMovements = StockMovement::query()->where('type', '!=', 'opening_balance')
            ->whereNotNull('carrying_value_after_cents')->whereDate('effective_date', '>=', $cutoverDate)->whereDate('effective_date', '<=', $date)
            ->orderBy('effective_date')->orderBy('id')->get()->groupBy('inventory_id');
        foreach ($latestMovements as $inventoryId => $movements) {
            $values[$inventoryId] = (int) $movements->last()->carrying_value_after_cents;
        }

        return [(int) $values->sum(), null];
    }
}
