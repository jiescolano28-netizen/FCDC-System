<?php

use App\Livewire\Accounting\FinancialStatements;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingPeriod;
use App\Models\AccountingYtdSummary;
use App\Services\Accounting\OpeningBooksService;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $readiness = Mockery::mock(OpeningBooksService::class);
    $readiness->shouldReceive('readiness')->andReturn([
        'production_activated' => true,
        'production' => 'Production activated',
    ]);
    app()->instance(OpeningBooksService::class, $readiness);
});

function createFinancialStatementsEmployee(array $permissions = ['accounting.view']): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'statements.viewer.'.fake()->unique()->numerify('####'),
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('statements-password'),
    ]), $permissions);
}

function statementAccount(string $code, string $classification, string $type, string $normalBalance): AccountingAccount
{
    return AccountingAccount::create([
        'code' => $code,
        'name' => 'Account '.$code,
        'type' => $type,
        'classification' => $classification,
        'normal_balance' => $normalBalance,
        'is_active' => true,
        'approved_at' => now(),
    ]);
}

function statementJournal(AccountingPostingPeriod $period, Employee $actor, string $date, string $source, array $lines, string $reference, string $status = 'posted', ?int $correctionOfId = null): AccountingJournal
{
    $journal = AccountingJournal::create([
        'book_key' => 'FCDC',
        'reference' => $reference,
        'source_type' => $source,
        'source_id' => $source === 'opening' ? 'FCDC' : $reference,
        'accounting_date' => $date,
        'posting_period_id' => $period->id,
        'correction_of_id' => $correctionOfId,
        'description' => $reference,
        'status' => 'draft',
        'prepared_by' => $actor->id,
    ]);
    foreach ($lines as [$account, $debit, $credit]) {
        $journal->lines()->create([
            'accounting_account_id' => $account->id,
            'debit_cents' => $debit,
            'credit_cents' => $credit,
        ]);
    }
    if ($status === 'posted') {
        $journal->forceFill([
            'status' => 'posted',
            'posted_by' => $actor->id,
            'posted_at' => now(),
        ])->save();
    }

    return $journal;
}

function statementPeriod(int $year): AccountingPostingPeriod
{
    return AccountingPostingPeriod::create([
        'book_key' => 'FCDC',
        'fiscal_year' => $year,
        'starts_on' => "$year-01-01",
        'ends_on' => "$year-12-31",
        'status' => 'open',
    ]);
}

test('financial statements is authenticated and accounting.view protects report access', function () {
    $this->get(route('accounting.financial-statements'))->assertRedirect(route('login'));

    $this->actingAs(createFinancialStatementsEmployee(['accounting.view']))
        ->get(route('accounting.financial-statements'))
        ->assertOk()
        ->assertSee('Financial Statements')
        ->assertSee('Income Statement')
        ->assertSee('Balance Sheet')
        ->assertSee('Accounting')
        ->assertSee(route('accounting.financial-statements'), false);

    $this->actingAs(createFinancialStatementsEmployee([]))
        ->get(route('accounting.financial-statements'))
        ->assertForbidden();
});

test('income statement classifies posted activity and excludes drafts, tax and fiscal closing entries', function () {
    $viewer = createFinancialStatementsEmployee();
    $period = statementPeriod(2026);
    $opening = statementAccount('1000', 'cash', 'Asset', 'debit');
    $sales = statementAccount('4000', 'sales', 'Revenue', 'credit');
    $cogs = statementAccount('5000', 'cost_of_goods_sold', 'Expense', 'debit');
    $operating = statementAccount('6000', 'operating_expense', 'Expense', 'debit');
    $otherIncome = statementAccount('4100', 'other_income', 'Revenue', 'credit');
    $otherExpense = statementAccount('6100', 'other_expense', 'Expense', 'debit');
    $vat = statementAccount('2200', 'output_vat', 'Liability', 'credit');

    statementJournal($period, $viewer, '2026-01-01', 'opening', [[$opening, 100000, 0], [$sales, 0, 100000]], 'OPEN-26');
    statementJournal($period, $viewer, '2026-03-01', 'sale', [[$opening, 11200, 0], [$sales, 0, 10000], [$vat, 0, 1200]], 'SALE-1');
    statementJournal($period, $viewer, '2026-03-01', 'sale-cost', [[$cogs, 3000, 0], [$opening, 0, 3000]], 'COGS-1');
    statementJournal($period, $viewer, '2026-03-02', 'expense', [[$operating, 2000, 0], [$opening, 0, 2000]], 'OPEX-1');
    statementJournal($period, $viewer, '2026-03-03', 'income', [[$opening, 500, 0], [$otherIncome, 0, 500]], 'OTHER-IN-1');
    statementJournal($period, $viewer, '2026-03-04', 'expense', [[$otherExpense, 700, 0], [$opening, 0, 700]], 'OTHER-OUT-1');
    $closing = statementJournal($period, $viewer, '2026-03-05', 'fiscal_year_closing', [[$sales, 10000, 0], [$opening, 0, 10000]], 'CLOSE-26');
    statementJournal($period, $viewer, '2026-04-06', 'reversal', [[$sales, 0, 10000], [$opening, 10000, 0]], 'CLOSE-REV-26', 'posted', $closing->id);

    statementJournal($period, $viewer, '2026-03-06', 'draft-sale', [[$sales, 0, 90000]], 'DRAFT-1', 'draft');

    Livewire::actingAs($viewer)->test(FinancialStatements::class)
        ->set('fromDate', '2026-03-01')
        ->set('toDate', '2026-03-31')
        ->assertSee('Sales')
        ->assertSee('Gross Profit')
        ->assertSee('Operating expenses')
        ->assertSee('Other income')
        ->assertSee('Other expenses')
        ->assertSee('Net Income')
        ->assertSee('PHP 100.00')
        ->assertSee('PHP 70.00')
        ->assertSee('PHP 20.00')
        ->assertSee('PHP 5.00')
        ->assertSee('PHP 7.00')
        ->assertSee('PHP 48.00')
        ->assertDontSee('PHP 1,000.00');

    Livewire::actingAs($viewer)->test(FinancialStatements::class)
        ->set('fromDate', '2026-04-01')
        ->set('toDate', '2026-04-30')
        ->assertSee('No posted income or expense activity in this period.')
        ->assertSee('<td>PHP 0.00</td>', false);
});

test('an eligible YTD report labels its approved pre-cutover summary and arbitrary earlier ranges are unavailable', function () {
    $viewer = createFinancialStatementsEmployee();
    $period = statementPeriod(2026);
    $opening = statementAccount('1000', 'cash', 'Asset', 'debit');
    $sales = statementAccount('4000', 'sales', 'Revenue', 'credit');
    $operating = statementAccount('6000', 'operating_expense', 'Expense', 'debit');
    statementJournal($period, $viewer, '2026-07-01', 'opening', [[$opening, 100000, 0], [$sales, 0, 100000]], 'OPEN-JUL');
    $summary = AccountingYtdSummary::create([
        'book_key' => 'FCDC',
        'fiscal_year' => 2026,
        'through_date' => '2026-06-30',
        'evidence_reference' => 'Approved historical schedule',
        'status' => 'draft',
        'prepared_by' => $viewer->id,
    ]);
    $summary->lines()->createMany([
        ['accounting_account_id' => $sales->id, 'amount_cents' => 50000],
        ['accounting_account_id' => $operating->id, 'amount_cents' => 15000],
    ]);
    $summary->forceFill([
        'status' => 'approved',
        'approved_by' => $viewer->id,
        'approved_at' => now(),
    ])->save();
    statementJournal($period, $viewer, '2026-07-02', 'sale', [[$opening, 22400, 0], [$sales, 0, 20000]], 'SALE-JUL');
    statementJournal($period, $viewer, '2026-07-02', 'expense', [[$operating, 4000, 0], [$opening, 0, 4000]], 'OPEX-JUL');

    Livewire::actingAs($viewer)->test(FinancialStatements::class)
        ->set('fromDate', '2026-01-01')
        ->set('toDate', '2026-07-31')
        ->assertSee('Approved pre-cutover YTD summary')
        ->assertSee('Historical summary through June 30, 2026')
        ->assertSee('PHP 700.00')
        ->assertSee('PHP 190.00')
        ->assertSee('PHP 510.00');

    Livewire::actingAs($viewer)->test(FinancialStatements::class)
        ->set('fromDate', '2026-06-01')
        ->set('toDate', '2026-07-31')
        ->assertSee('Selected range begins before approved accounting cutover coverage.')
        ->assertSee('unavailable');
});

test('report distinguishes unavailable books from a genuine no-activity period and displays Manila scope and generation time', function () {
    $viewer = createFinancialStatementsEmployee();

    Livewire::actingAs($viewer)->test(FinancialStatements::class)
        ->set('fromDate', '2026-03-01')
        ->set('toDate', '2026-03-31')
        ->assertSee('Posted accounting cutover coverage is unavailable')
        ->assertSee('unavailable');

    $period = statementPeriod(2026);
    $cash = statementAccount('1000', 'cash', 'Asset', 'debit');
    $capital = statementAccount('3000', 'capital', 'Equity', 'credit');
    statementAccount('4000', 'sales', 'Revenue', 'credit');
    statementJournal($period, $viewer, '2026-01-01', 'opening', [[$cash, 100000, 0], [$capital, 0, 100000]], 'OPEN-ZERO');

    Livewire::actingAs($viewer)->test(FinancialStatements::class)
        ->set('fromDate', '2026-03-01')
        ->set('toDate', '2026-03-31')
        ->assertSee('No posted income or expense activity in this period.')
        ->assertSee('Scope: approved FCDC posted accounting activity')
        ->assertSee('Asia/Manila');

    AccountingAccount::create([
        'code' => '6100',
        'name' => 'Pending account',
        'type' => 'Expense',
        'classification' => 'operating_expense',
        'normal_balance' => 'debit',
        'is_active' => true,
    ]);
    Livewire::actingAs($viewer)->test(FinancialStatements::class)
        ->set('fromDate', '2026-03-01')
        ->set('toDate', '2026-03-31')
        ->assertSee('await approval')
        ->assertSee('Income Statement unavailable.');
});

test('income statement labels and signs an exact net loss', function () {
    $viewer = createFinancialStatementsEmployee();
    $period = statementPeriod(2026);
    $cash = statementAccount('1000', 'cash', 'Asset', 'debit');
    $sales = statementAccount('4000', 'sales', 'Revenue', 'credit');
    $cogs = statementAccount('5000', 'cost_of_goods_sold', 'Expense', 'debit');
    $otherExpense = statementAccount('6100', 'other_expense', 'Expense', 'debit');
    statementJournal($period, $viewer, '2026-01-01', 'opening', [[$cash, 100000, 0], [$sales, 0, 100000]], 'OPEN-LOSS');
    statementJournal($period, $viewer, '2026-02-01', 'sale', [[$cash, 10000, 0], [$sales, 0, 10000]], 'LOSS-SALE');
    statementJournal($period, $viewer, '2026-02-02', 'expense', [[$cogs, 12000, 0], [$cash, 0, 12000]], 'LOSS-COGS');
    statementJournal($period, $viewer, '2026-02-03', 'expense', [[$otherExpense, 1000, 0], [$cash, 0, 1000]], 'LOSS-OTHER');

    Livewire::actingAs($viewer)->test(FinancialStatements::class)
        ->set('fromDate', '2026-02-01')
        ->set('toDate', '2026-02-28')
        ->assertSee('Gross Profit')
        ->assertSee('−PHP 20.00')
        ->assertSee('Net Loss')
        ->assertSee('−PHP 30.00');
});

test('balance sheet presents classified as-of balances and current earnings without a balancing plug', function () {
    $viewer = createFinancialStatementsEmployee();
    $period = statementPeriod(2026);
    $cash = statementAccount('1000', 'cash', 'Asset', 'debit');
    $equipment = statementAccount('1500', 'equipment', 'Asset', 'debit');
    $payable = statementAccount('2000', 'accounts_payable', 'Liability', 'credit');
    $outputVat = statementAccount('2200', 'output_vat', 'Liability', 'credit');
    $capital = statementAccount('3000', 'capital', 'Equity', 'credit');
    $retained = statementAccount('3100', 'retained_earnings', 'Equity', 'credit');
    $sales = statementAccount('4000', 'sales', 'Revenue', 'credit');
    $expense = statementAccount('6100', 'operating_expense', 'Expense', 'debit');
    $contraAsset = statementAccount('1090', 'other_current_asset', 'Asset', 'debit');

    statementJournal($period, $viewer, '2026-01-01', 'opening', [
        [$cash, 100000, 0], [$equipment, 100000, 0], [$payable, 0, 20000],
        [$capital, 0, 80000], [$retained, 0, 100000],
    ], 'BS-OPEN');
    statementJournal($period, $viewer, '2026-03-01', 'sale', [
        [$cash, 56000, 0], [$sales, 0, 50000], [$outputVat, 0, 6000],
    ], 'BS-SALE');
    statementJournal($period, $viewer, '2026-03-02', 'expense', [
        [$expense, 20000, 0], [$cash, 0, 20000],
    ], 'BS-EXPENSE');
    statementJournal($period, $viewer, '2026-03-03', 'adjustment', [
        [$expense, 500, 0], [$contraAsset, 0, 500],
    ], 'BS-CONTRA');

    Livewire::actingAs($viewer)->test(FinancialStatements::class)
        ->set('statementType', 'balance-sheet')
        ->set('toDate', '2026-12-30')
        ->assertSee('As of December 30, 2026')
        ->assertSee('Assets')
        ->assertSee('Liabilities')
        ->assertSee('Equity')
        ->assertSee('Cash')
        ->assertSee('Equipment')
        ->assertSee('Accounts payable')
        ->assertSee('Output VAT')
        ->assertSee('Capital')
        ->assertSee('Retained / accumulated earnings')
        ->assertSee('Unclosed earnings')
        ->assertSee('PHP 1,360.00')
        ->assertSee('PHP 1,000.00')
        ->assertSee('PHP 260.00')
        ->assertSee('PHP 800.00')
        ->assertSee('−PHP 5.00')
        ->assertSee('PHP 295.00')
        ->assertSee('PHP 2,355.00')
        ->assertSee('Accounting equation balances');
    $closing = statementJournal($period, $viewer, '2026-12-31', 'fiscal_year_closing', [
        [$sales, 50000, 0], [$expense, 0, 20500], [$retained, 0, 29500],
    ], 'BS-CLOSE');
    Livewire::actingAs($viewer)->test(FinancialStatements::class)
        ->set('statementType', 'balance-sheet')
        ->set('toDate', '2026-12-31')
        ->assertSee('PHP 1,295.00')
        ->assertSee('PHP 0.00')
        ->assertSee('Accounting equation balances');
    $reopenPeriod = statementPeriod(2027);
    statementJournal($reopenPeriod, $viewer, '2027-01-02', 'reversal', [
        [$sales, 0, 50000], [$expense, 20500, 0], [$retained, 29500, 0],
    ], 'BS-CLOSE-REVERSAL', 'posted', $closing->id);
    Livewire::actingAs($viewer)->test(FinancialStatements::class)
        ->set('statementType', 'balance-sheet')
        ->set('toDate', '2026-12-31')
        ->assertSee('PHP 1,295.00')
        ->assertSee('PHP 0.00')
        ->assertSee('Accounting equation balances');
});

test('balance sheet reports an accounting error for an unbalanced posted ledger without inventing equity', function () {
    $viewer = createFinancialStatementsEmployee();
    $period = statementPeriod(2026);
    $cash = statementAccount('1000', 'cash', 'Asset', 'debit');
    $capital = statementAccount('3000', 'capital', 'Equity', 'credit');
    statementJournal($period, $viewer, '2026-01-01', 'opening', [
        [$cash, 100000, 0], [$capital, 0, 100000],
    ], 'BS-CORRUPTED-OPENING');
    statementJournal($period, $viewer, '2026-02-01', 'corrupted', [
        [$cash, 100, 0],
    ], 'BS-CORRUPTED-POSTING');

    Livewire::actingAs($viewer)->test(FinancialStatements::class)
        ->set('statementType', 'balance-sheet')
        ->set('toDate', '2026-02-01')
        ->assertSee('Accounting error: Assets do not equal Liabilities plus Equity.')
        ->assertSee('Difference: PHP 1.00')
        ->assertDontSee('Accounting equation balances');
});

test('balance sheet includes approved pre-cutover year-to-date earnings separately from opening equity', function () {
    $viewer = createFinancialStatementsEmployee();
    $period = statementPeriod(2026);
    $cash = statementAccount('1000', 'cash', 'Asset', 'debit');
    $capital = statementAccount('3000', 'capital', 'Equity', 'credit');
    $sales = statementAccount('4000', 'sales', 'Revenue', 'credit');
    statementJournal($period, $viewer, '2026-07-01', 'opening', [
        [$cash, 100000, 0], [$capital, 0, 70000], [$sales, 0, 30000],
    ], 'BS-YTD-OPEN');
    $summary = AccountingYtdSummary::create([
        'book_key' => 'FCDC',
        'fiscal_year' => 2026,
        'through_date' => '2026-06-30',
        'evidence_reference' => 'Approved year-to-date income schedule',
        'status' => 'draft',
        'prepared_by' => $viewer->id,
    ]);
    $summary->lines()->create(['accounting_account_id' => $sales->id, 'amount_cents' => 30000]);
    $summary->forceFill([
        'status' => 'approved',
        'approved_by' => $viewer->id,
        'approved_at' => now(),
    ])->save();

    Livewire::actingAs($viewer)->test(FinancialStatements::class)
        ->set('statementType', 'balance-sheet')
        ->set('toDate', '2026-07-01')
        ->assertSee('PHP 300.00')
        ->assertSee('PHP 1,000.00')
        ->assertSee('Accounting equation balances');
});

test('balance sheet excludes posted ledger history before the approved cutover', function () {
    $viewer = createFinancialStatementsEmployee();
    $priorPeriod = statementPeriod(2025);
    $period = statementPeriod(2026);
    $cash = statementAccount('1000', 'cash', 'Asset', 'debit');
    $capital = statementAccount('3000', 'capital', 'Equity', 'credit');
    statementJournal($priorPeriod, $viewer, '2025-12-31', 'manual', [
        [$cash, 50000, 0], [$capital, 0, 50000],
    ], 'BS-PRECUTOVER');
    statementJournal($period, $viewer, '2026-01-01', 'opening', [
        [$cash, 100000, 0], [$capital, 0, 100000],
    ], 'BS-CUTOVER');

    Livewire::actingAs($viewer)->test(FinancialStatements::class)
        ->set('statementType', 'balance-sheet')
        ->set('toDate', '2026-01-01')
        ->assertSee('PHP 1,000.00')
        ->assertDontSee('PHP 1,500.00')
        ->assertSee('Accounting equation balances');
});

test('balance sheet preserves unclosed earnings from a used income account after deactivation', function () {
    $viewer = createFinancialStatementsEmployee();
    $period = statementPeriod(2026);
    $cash = statementAccount('1000', 'cash', 'Asset', 'debit');
    $capital = statementAccount('3000', 'capital', 'Equity', 'credit');
    $sales = statementAccount('4000', 'sales', 'Revenue', 'credit');
    statementJournal($period, $viewer, '2026-01-01', 'opening', [
        [$cash, 100000, 0], [$capital, 0, 100000],
    ], 'BS-INACTIVE-OPEN');
    statementJournal($period, $viewer, '2026-02-01', 'sale', [
        [$cash, 10000, 0], [$sales, 0, 10000],
    ], 'BS-INACTIVE-SALE');
    $sales->markUsed();
    $sales->update(['is_active' => false]);

    Livewire::actingAs($viewer)->test(FinancialStatements::class)
        ->set('statementType', 'balance-sheet')
        ->set('toDate', '2026-02-01')
        ->assertSee('PHP 100.00')
        ->assertSee('Accounting equation balances');
    $sales->update(['is_active' => true]);
    Livewire::actingAs($viewer)->test(FinancialStatements::class)
        ->set('statementType', 'balance-sheet')
        ->set('toDate', '2026-02-01')
        ->assertSee('Balance Sheet unavailable.')
        ->assertSee('active accounts await approval');
});
