<?php

use App\Livewire\Accounting\AccountingPeriods;
use App\Livewire\Accounting\FinancialStatements;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingPeriod;
use App\Models\AccountingYtdSummary;
use App\Models\Employee;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Accounting\FiscalYearClosingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function fiscalCloseActor(array $permissions, string $name): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => $name,
        'email' => $name.'@example.test',
        'password' => bcrypt('password'),
    ]), $permissions);
}

function fiscalCloseAccount(string $code, string $classification, string $type, string $normalBalance): AccountingAccount
{
    return AccountingAccount::create([
        'code' => $code, 'name' => 'Account '.$code, 'type' => $type,
        'classification' => $classification, 'normal_balance' => $normalBalance,
        'is_active' => true, 'approved_at' => now(),
    ]);
}

function fiscalCloseJournal(AccountingPostingPeriod $period, Employee $actor, string $reference, string $date, string $source, array $lines): AccountingJournal
{
    $journal = AccountingJournal::create([
        'book_key' => 'FCDC', 'reference' => $reference, 'source_type' => $source,
        'source_id' => $source === 'opening' ? 'FCDC' : $reference,
        'accounting_date' => $date, 'posting_period_id' => $period->id,
        'description' => $reference, 'status' => 'draft', 'prepared_by' => $actor->id,
    ]);
    foreach ($lines as [$account, $debit, $credit]) {
        $journal->lines()->create([
            'accounting_account_id' => $account->id, 'debit_cents' => $debit, 'credit_cents' => $credit,
        ]);
    }
    $journal->forceFill(['status' => 'posted', 'posted_by' => $actor->id, 'posted_at' => now()])->save();

    return $journal;
}

function closedFiscalPeriod(int $year, int $month): AccountingPostingPeriod
{
    return AccountingPostingPeriod::create([
        'book_key' => 'FCDC', 'fiscal_year' => $year, 'period_month' => $month,
        'starts_on' => sprintf('%04d-%02d-01', $year, $month),
        'ends_on' => CarbonImmutable::create($year, $month, 1)->endOfMonth()->toDateString(),
        'status' => 'closed', 'closed_at' => now(),
    ]);
}

test('authorized fiscal close transfers annual income once without changing the income statement', function () {
    $year = 2025;
    $actor = fiscalCloseActor(['accounting.view', 'accounting.close-fiscal-year'], 'fiscal-close-actor');
    $periods = collect(range(1, 12))->map(fn ($month) => closedFiscalPeriod($year, $month));
    $cash = fiscalCloseAccount('1000', 'cash', 'Asset', 'debit');
    $capital = fiscalCloseAccount('3000', 'capital', 'Equity', 'credit');
    $retained = fiscalCloseAccount('3100', 'retained_earnings', 'Equity', 'credit');
    $sales = fiscalCloseAccount('4000', 'sales', 'Revenue', 'credit');
    $expense = fiscalCloseAccount('6000', 'operating_expense', 'Expense', 'debit');
    fiscalCloseJournal($periods[0], $actor, 'FC-OPEN', "$year-01-01", 'opening', [[$cash, 10000, 0], [$capital, 0, 10000]]);
    fiscalCloseJournal($periods[2], $actor, 'FC-SALE', "$year-03-01", 'sale', [[$cash, 5000, 0], [$sales, 0, 5000]]);
    fiscalCloseJournal($periods[3], $actor, 'FC-EXPENSE', "$year-04-01", 'expense', [[$expense, 2000, 0], [$cash, 0, 2000]]);

    $closing = app(FiscalYearClosingService::class)->close($year, $retained->id, 'Year reconciled', $actor);

    expect($closing->source_type)->toBe('fiscal_year_closing')
        ->and($closing->accounting_date->toDateString())->toBe("$year-12-31")
        ->and($closing->lines->sum('debit_cents'))->toBe(5000)
        ->and($closing->lines->sum('credit_cents'))->toBe(5000)
        ->and($closing->correction_of_id)->toBeNull();

    Livewire::actingAs($actor)->test(FinancialStatements::class)
        ->set('fromDate', "$year-01-01")->set('toDate', "$year-12-31")
        ->assertSee('Net Income')->assertSee('PHP 30.00');
    Livewire::actingAs($actor)->test(FinancialStatements::class)
        ->set('statementType', 'balance-sheet')->set('toDate', "$year-12-31")
        ->assertSee('Retained / accumulated earnings')->assertSee('PHP 30.00')
        ->assertSee('Unclosed earnings')->assertSee('PHP 0.00');
    $this->actingAs($actor)->get(route('accounting.general-ledger', [
        'accountId' => $retained->id,
        'fromDate' => "$year-12-31",
        'toDate' => "$year-12-31",
    ]))->assertOk()->assertSee($closing->reference);

    expect(fn () => app(FiscalYearClosingService::class)->close($year, $retained->id, 'Duplicate close', $actor))
        ->toThrow(ValidationException::class);
    Livewire::actingAs($actor)->test(AccountingPeriods::class)
        ->set('year', $year)->assertSee('Fiscal year closed')
        ->assertSee($closing->reference)->assertSee('Year reconciled');
});

test('fiscal close rejects unauthorized actors and unclosed monthly chains', function () {
    $year = 2025;
    $actor = fiscalCloseActor([], 'fiscal-close-denied');
    $retained = fiscalCloseAccount('3100', 'retained_earnings', 'Equity', 'credit');
    foreach (range(1, 12) as $month) {
        closedFiscalPeriod($year, $month);
    }
    closedFiscalPeriod($year + 1, 1)->forceFill(['status' => 'open'])->save();

    expect(fn () => app(FiscalYearClosingService::class)->close($year, $retained->id, 'Year reconciled', $actor))
        ->toThrow(HttpException::class);
});

test('reopening reverses the linked fiscal close and reconciliation permits a preserved-history reclose', function () {
    $year = 2025;
    $actor = fiscalCloseActor([
        'accounting.view', 'accounting.close-fiscal-year', 'accounting.reopen-period',
    ], 'fiscal-reclose-actor');
    $periods = collect(range(1, 12))->map(fn ($month) => closedFiscalPeriod($year, $month));
    $cash = fiscalCloseAccount('1000', 'cash', 'Asset', 'debit');
    $capital = fiscalCloseAccount('3000', 'capital', 'Equity', 'credit');
    $retained = fiscalCloseAccount('3100', 'retained_earnings', 'Equity', 'credit');
    $sales = fiscalCloseAccount('4000', 'sales', 'Revenue', 'credit');
    $expense = fiscalCloseAccount('6000', 'operating_expense', 'Expense', 'debit');
    fiscalCloseJournal($periods[0], $actor, 'FR-OPEN', "$year-01-01", 'opening', [[$cash, 10000, 0], [$capital, 0, 10000]]);
    fiscalCloseJournal($periods[2], $actor, 'FR-SALE', "$year-03-01", 'sale', [[$cash, 5000, 0], [$sales, 0, 5000]]);
    fiscalCloseJournal($periods[3], $actor, 'FR-EXPENSE', "$year-04-01", 'expense', [[$expense, 2000, 0], [$cash, 0, 2000]]);
    $closingService = app(FiscalYearClosingService::class);
    $firstClosing = $closingService->close($year, $retained->id, 'First reviewed close', $actor);

    $reopened = app(AccountingPeriodService::class)->reopen(
        $periods[0]->id,
        $periods->slice(1)->pluck('id')->all(),
        'Correct prior-year activity',
        $actor,
    );
    $reversal = AccountingJournal::query()->where('correction_of_id', $firstClosing->id)
        ->where('status', 'posted')->firstOrFail();
    expect($reopened)->toHaveCount(12)
        ->and($reversal->correction_reason)->toBe('Correct prior-year activity')
        ->and($reversal->accounting_date->toDateString())->toBe('2025-12-31')
        ->and($reversal->posting_period_id)->toBe($periods[11]->id)
        ->and($reversal->lines->sum('debit_cents'))->toBe(5000)
        ->and($reversal->lines->sum('credit_cents'))->toBe(5000);

    $periods->each(fn (AccountingPostingPeriod $period) => $period->fresh()->forceFill(['status' => 'closed'])->save());
    $secondClosing = $closingService->close($year, $retained->id, 'Reconciled reclose', $actor);
    expect($secondClosing->id)->not->toBe($firstClosing->id)
        ->and($secondClosing->source_id)->toBe('2025:2');

    Livewire::actingAs($actor)->test(FinancialStatements::class)
        ->set('fromDate', "$year-01-01")->set('toDate', "$year-12-31")
        ->assertSee('PHP 30.00')->assertSee($firstClosing->reference)
        ->assertSee($secondClosing->reference)->assertSee($reversal->reference);
    Livewire::actingAs($actor)->test(FinancialStatements::class)
        ->set('statementType', 'balance-sheet')->set('toDate', "$year-12-31")
        ->assertSee('PHP 30.00')->assertSee('Unclosed earnings')
        ->assertSee('PHP 0.00')->assertSee('Accounting equation balances');
});
test('fiscal close transfers approved pre-cutover YTD balances once', function () {
    $year = 2025;
    $actor = fiscalCloseActor(['accounting.close-fiscal-year'], 'fiscal-ytd-close-actor');
    $periods = collect(range(1, 12))->map(fn ($month) => closedFiscalPeriod($year, $month));
    $cash = fiscalCloseAccount('1000', 'cash', 'Asset', 'debit');
    $capital = fiscalCloseAccount('3000', 'capital', 'Equity', 'credit');
    $retained = fiscalCloseAccount('3100', 'retained_earnings', 'Equity', 'credit');
    $sales = fiscalCloseAccount('4000', 'sales', 'Revenue', 'credit');
    $expense = fiscalCloseAccount('6000', 'operating_expense', 'Expense', 'debit');
    fiscalCloseJournal($periods[6], $actor, 'FYTD-OPEN', "$year-07-01", 'opening', [
        [$cash, 10000, 0], [$capital, 0, 7000], [$sales, 0, 5000], [$expense, 2000, 0],
    ]);
    $summary = AccountingYtdSummary::create([
        'book_key' => 'FCDC', 'fiscal_year' => $year, 'through_date' => "$year-06-30",
        'evidence_reference' => 'Reviewed pre-cutover income schedule',
        'status' => 'draft', 'prepared_by' => $actor->id,
    ]);
    $summary->lines()->createMany([
        ['accounting_account_id' => $sales->id, 'amount_cents' => 5000],
        ['accounting_account_id' => $expense->id, 'amount_cents' => 2000],
    ]);
    $summary->forceFill([
        'status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(),
    ])->save();

    $closing = app(FiscalYearClosingService::class)->close($year, $retained->id, 'Reviewed cutover year close', $actor);

    expect($closing->lines->sum('debit_cents'))->toBe(5000)
        ->and($closing->lines->sum('credit_cents'))->toBe(5000)
        ->and($closing->lines->firstWhere('accounting_account_id', $retained->id)?->credit_cents)->toBe(3000);
});
