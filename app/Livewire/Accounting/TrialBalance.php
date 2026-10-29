<?php

namespace App\Livewire\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingJournalLine;
use App\Services\Accounting\AccountingPositionSchedules;
use App\Services\Accounting\OpeningBooksService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
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
        $positionSchedules = app(AccountingPositionSchedules::class);

        $cutover = AccountingJournal::query()
            ->where('book_key', 'FCDC')->where('source_type', 'opening')->where('source_id', 'FCDC')
            ->where('status', 'posted')->value('accounting_date');
        $cutoverDate = $cutover ? CarbonImmutable::parse($cutover, 'Asia/Manila')->toDateString() : null;
        $readiness = app(OpeningBooksService::class)->readiness();
        $dateError = $this->dateRangeError();
        $openingWarning = ! $cutoverDate || ! ($readiness['production_activated'] ?? false)
            ? 'Opening balances or cutover are incomplete. This report shows posted journal activity only; supplier and inventory reconciliation may still be pending.'
            : null;
        $accounts = collect();
        $totalDebitsCents = 0;
        $totalCreditsCents = 0;
        $periodDebitsCents = 0;
        $periodCreditsCents = 0;
        $unadjustedDebitsCents = 0;
        $unadjustedCreditsCents = 0;
        $adjustmentDebitsCents = 0;
        $adjustmentCreditsCents = 0;
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
                ->select(
                    'accounting_journal_lines.*',
                    'accounting_journals.accounting_date as posted_date',
                    'accounting_journals.entry_type as journal_entry_type',
                )
                ->get();
            $accountTotals = $ledgerLines->groupBy('accounting_account_id')->map(function ($lines) {
                $isAdjusting = fn ($line) => ($line->journal_entry_type ?? AccountingJournal::ENTRY_TYPE_STANDARD) === AccountingJournal::ENTRY_TYPE_ADJUSTING;
                $periodLines = $lines->filter(fn ($line) => $line->posted_date >= $this->fromDate);
                $periodAdjusting = $periodLines->filter($isAdjusting);
                $periodStandard = $periodLines->reject($isAdjusting);
                $cumulativeNet = (int) $lines->sum('debit_cents') - (int) $lines->sum('credit_cents');
                $adjustmentDebit = (int) $periodAdjusting->sum('debit_cents');
                $adjustmentCredit = (int) $periodAdjusting->sum('credit_cents');
                $standardPeriodDebit = (int) $periodStandard->sum('debit_cents');
                $standardPeriodCredit = (int) $periodStandard->sum('credit_cents');
                $unadjustedNet = $cumulativeNet - ($adjustmentDebit - $adjustmentCredit);
                $openingNet = $unadjustedNet - ($standardPeriodDebit - $standardPeriodCredit);

                return [
                    'opening_debit' => max(0, $openingNet),
                    'opening_credit' => max(0, -$openingNet),
                    'period_debit' => $standardPeriodDebit,
                    'period_credit' => $standardPeriodCredit,
                    'unadjusted_debit' => max(0, $unadjustedNet),
                    'unadjusted_credit' => max(0, -$unadjustedNet),
                    'adjustment_debit' => $adjustmentDebit,
                    'adjustment_credit' => $adjustmentCredit,
                    'adjusted_debit' => max(0, $cumulativeNet),
                    'adjusted_credit' => max(0, -$cumulativeNet),
                ];
            });
            $accounts = AccountingAccount::query()
                ->where(fn (Builder $query) => $query
                    ->where(fn (Builder $approvedAccounts) => $approvedAccounts->whereNotNull('approved_at')->where('is_active', true))
                    ->orWhereIn('id', $accountTotals->keys()))
                ->orderBy('code')->get()
                ->map(function (AccountingAccount $account) use ($accountTotals, &$totalDebitsCents, &$totalCreditsCents, &$periodDebitsCents, &$periodCreditsCents, &$unadjustedDebitsCents, &$unadjustedCreditsCents, &$adjustmentDebitsCents, &$adjustmentCreditsCents) {
                    $totals = $accountTotals->get($account->id, [
                        'opening_debit' => 0, 'opening_credit' => 0, 'period_debit' => 0, 'period_credit' => 0,
                        'unadjusted_debit' => 0, 'unadjusted_credit' => 0, 'adjustment_debit' => 0, 'adjustment_credit' => 0,
                        'adjusted_debit' => 0, 'adjusted_credit' => 0,
                    ]);
                    $totalDebitsCents += $totals['adjusted_debit'];
                    $totalCreditsCents += $totals['adjusted_credit'];
                    $periodDebitsCents += $totals['period_debit'];
                    $periodCreditsCents += $totals['period_credit'];
                    $unadjustedDebitsCents += $totals['unadjusted_debit'];
                    $unadjustedCreditsCents += $totals['unadjusted_credit'];
                    $adjustmentDebitsCents += $totals['adjustment_debit'];
                    $adjustmentCreditsCents += $totals['adjustment_credit'];

                    return [
                        'account' => $account,
                        'opening_debit_cents' => $totals['opening_debit'],
                        'opening_credit_cents' => $totals['opening_credit'],
                        'period_debit_cents' => $totals['period_debit'],
                        'period_credit_cents' => $totals['period_credit'],
                        'unadjusted_debit_cents' => $totals['unadjusted_debit'],
                        'unadjusted_credit_cents' => $totals['unadjusted_credit'],
                        'adjustment_debit_cents' => $totals['adjustment_debit'],
                        'adjustment_credit_cents' => $totals['adjustment_credit'],
                        'closing_debit_cents' => $totals['adjusted_debit'],
                        'closing_credit_cents' => $totals['adjusted_credit'],
                    ];
                })->values();

            $apAccounts = $accounts->filter(fn ($row) => $row['account']->classification === 'accounts_payable');
            $apBookCents = (int) $apAccounts->sum(fn ($row) => $row['closing_credit_cents'] - $row['closing_debit_cents']);
            if ($apAccounts->isNotEmpty()) {
                $apScheduleCents = $positionSchedules->accountsPayableAsOf($this->toDate);
                $apMismatch = $apBookCents !== $apScheduleCents;
            }

            $inventoryAccounts = $accounts->filter(fn ($row) => $row['account']->classification === 'inventory');
            $inventoryBookCents = (int) $inventoryAccounts->sum(fn ($row) => $row['closing_debit_cents'] - $row['closing_credit_cents']);
            if ($inventoryAccounts->isNotEmpty()) {
                if ($cutoverDate === null) {
                    $inventoryCoverageError = 'An approved opening inventory valuation matching accounting cutover is required.';
                } else {
                    [$inventoryScheduleCents, $inventoryCoverageError] = $positionSchedules->inventoryValueAsOf($this->toDate, $cutoverDate);
                    $inventoryMismatch = $inventoryScheduleCents !== null && $inventoryBookCents !== $inventoryScheduleCents;
                }
            }
        }

        $hasAdjustments = $adjustmentDebitsCents !== 0 || $adjustmentCreditsCents !== 0;

        return view('livewire.accounting.trial-balance', [
            'cutoverDate' => $cutoverDate,
            'openingWarning' => $openingWarning,
            'dateError' => $dateError,
            'accounts' => $accounts,
            'totalDebitsCents' => $totalDebitsCents,
            'totalCreditsCents' => $totalCreditsCents,
            'periodDebitsCents' => $periodDebitsCents,
            'periodCreditsCents' => $periodCreditsCents,
            'unadjustedDebitsCents' => $unadjustedDebitsCents,
            'unadjustedCreditsCents' => $unadjustedCreditsCents,
            'adjustmentDebitsCents' => $adjustmentDebitsCents,
            'adjustmentCreditsCents' => $adjustmentCreditsCents,
            'unadjustedDifferenceCents' => abs($unadjustedDebitsCents - $unadjustedCreditsCents),
            'adjustmentDifferenceCents' => abs($adjustmentDebitsCents - $adjustmentCreditsCents),
            'adjustedDifferenceCents' => abs($totalDebitsCents - $totalCreditsCents),
            'hasAdjustments' => $hasAdjustments,
            'isUnadjustedBalanced' => $dateError === null && $unadjustedDebitsCents === $unadjustedCreditsCents,
            'isAdjustedBalanced' => $dateError === null && $totalDebitsCents === $totalCreditsCents,
            'isBalanced' => $dateError === null && $totalDebitsCents === $totalCreditsCents,
            'apBookCents' => $apBookCents,
            'apScheduleCents' => $apScheduleCents,
            'apMismatch' => $apMismatch,
            'inventoryBookCents' => $inventoryBookCents,
            'inventoryScheduleCents' => $inventoryScheduleCents,
            'inventoryMismatch' => $inventoryMismatch,
            'inventoryCoverageError' => $inventoryCoverageError,
            'hasActivity' => $periodDebitsCents !== 0 || $periodCreditsCents !== 0 || $hasAdjustments,
        ])->layout('layouts.app', ['title' => 'Trial Balance']);
    }

    private function dateRangeError(): ?string
    {
        if (! CarbonImmutable::hasFormat($this->fromDate, 'Y-m-d') || ! CarbonImmutable::hasFormat($this->toDate, 'Y-m-d')) {
            return 'Choose a valid start and end date.';
        }
        $from = CarbonImmutable::createFromFormat('!Y-m-d', $this->fromDate, 'Asia/Manila');
        $to = CarbonImmutable::createFromFormat('!Y-m-d', $this->toDate, 'Asia/Manila');
        if (! $from || ! $to || $from->format('Y-m-d') !== $this->fromDate || $to->format('Y-m-d') !== $this->toDate || $from->gt($to)) {
            return 'Choose a valid inclusive date range.';
        }
        return null;
    }
}
