<?php

use App\Livewire\Accounting\AccountingOverview;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingPeriod;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\OpeningInventoryValuation;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierOpeningInvoice;
use App\Services\Accounting\OpeningBooksService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createAccountingOverviewEmployee(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'accounting.viewer',
        'email' => 'accounting@example.com',
        'password' => Hash::make('accounting-password'),
    ]), ['accounting.view']);
}
function activateOverviewBooksForTest(): void
{
    $readiness = \Mockery::mock(OpeningBooksService::class);
    $readiness->shouldReceive('readiness')->andReturn([
        'production_activated' => true,
        'production' => 'Production activated',
    ]);
    app()->instance(OpeningBooksService::class, $readiness);
}


function overviewAccount(string $code, string $classification, string $type, string $balance): AccountingAccount
{
    return AccountingAccount::create([
        'code' => $code,
        'name' => "Overview {$code}",
        'type' => $type,
        'classification' => $classification,
        'normal_balance' => $balance,
        'is_active' => true,
        'approved_at' => now(),
    ]);
}

function overviewPostJournal(Employee $actor, string $reference, string $date, array $lines, string $source = 'manual', string $status = 'posted'): AccountingJournal
{
    $year = (int) substr($date, 0, 4);
    $period = AccountingPostingPeriod::firstOrCreate(
        ['book_key' => 'FCDC', 'fiscal_year' => $year],
        ['starts_on' => "{$year}-01-01", 'ends_on' => "{$year}-12-31", 'status' => 'open'],
    );
    $journal = AccountingJournal::create([
        'book_key' => 'FCDC',
        'reference' => $reference,
        'source_type' => $source,
        'source_id' => $source === 'opening' ? 'FCDC' : $reference,
        'accounting_date' => $date,
        'posting_period_id' => $period->id,
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
        $journal->forceFill(['status' => 'posted', 'posted_by' => $actor->id, 'posted_at' => now()])->save();
    }

    return $journal;
}

test('accounting overview shows period flows separately from carried closing positions', function () {
    $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-10 12:00:00', 'Asia/Manila'));

    $viewer = createAccountingOverviewEmployee();
    $cash = overviewAccount('1000', 'cash', 'Asset', 'debit');
    $cardClearing = overviewAccount('1010', 'card_clearing', 'Asset', 'debit');
    $receivable = overviewAccount('1100', 'accounts_receivable', 'Asset', 'debit');
    $inventory = overviewAccount('1200', 'inventory', 'Asset', 'debit');
    $payable = overviewAccount('2000', 'accounts_payable', 'Liability', 'credit');
    $outputVat = overviewAccount('2200', 'output_vat', 'Liability', 'credit');
    $sales = overviewAccount('4000', 'sales', 'Revenue', 'credit');
    $cogs = overviewAccount('5000', 'cost_of_goods_sold', 'Expense', 'debit');
    $expense = overviewAccount('6000', 'operating_expense', 'Expense', 'debit');
    $equity = overviewAccount('3000', 'capital', 'Equity', 'credit');

    $item = Inventory::create([
        'name' => 'Opening inventory item',
        'category' => 'Building materials',
        'qty' => '200.00',
        'unit' => 'piece',
        'unit_cost' => '100.00',
        'reorder_level' => '0.00',
    ]);
    $valuation = OpeningInventoryValuation::create([
        'book_key' => 'FCDC',
        'cutover_date' => '2026-09-01',
        'evidence_reference' => 'Overview opening schedule',
        'status' => 'draft',
        'prepared_by' => $viewer->id,
    ]);
    $valuation->lines()->create(['inventory_id' => $item->id, 'quantity' => '200.00', 'carrying_value_cents' => 20000]);
    $valuation->forceFill(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $viewer->id])->save();
    $opening = overviewPostJournal($viewer, 'OPEN-OVERVIEW', '2026-09-01', [
        [$cash, 50000, 0], [$receivable, 7000, 0], [$inventory, 20000, 0],
        [$payable, 0, 30000], [$equity, 0, 47000],
    ], 'opening', 'draft');
    $supplier = Supplier::create(['code' => 'SUP-OVERVIEW', 'name' => 'Overview Supplier', 'created_by' => $viewer->id]);
    SupplierOpeningInvoice::create([
        'supplier_id' => $supplier->id, 'supplier_code_snapshot' => $supplier->code,
        'supplier_name_snapshot' => $supplier->name, 'invoice_number' => 'OPENING-AP',
        'invoice_number_normalized' => 'OPENING-AP', 'recognition_date' => '2026-09-01',
        'due_date' => '2026-09-30', 'amount_cents' => 30000, 'description' => 'Opening payable',
        'status' => 'posted', 'opening_journal_id' => $opening->id, 'prepared_by' => $viewer->id,
        'posted_at' => now(), 'posted_by' => $viewer->id, 'approved_at' => now(), 'approved_by' => $viewer->id,
    ]);
    $opening->forceFill(['status' => 'posted', 'posted_by' => $viewer->id, 'posted_at' => now()])->save();
    $sale = overviewPostJournal($viewer, 'OCT-SALE', '2026-10-03', [
        [$cash, 10000, 0], [$sales, 0, 10000], [$cardClearing, 1200, 0], [$outputVat, 0, 1200],
        [$cogs, 3000, 0], [$inventory, 0, 3000],
    ], 'sale');
    StockMovement::create([
        'inventory_id' => $item->id, 'posted_by' => $viewer->id, 'type' => 'stock_out',
        'quantity' => '-30.00', 'reason_category' => 'pos_sale', 'reference' => 'OCT-SALE',
        'effective_date' => '2026-10-03', 'posted_at' => now(), 'value_cents' => -3000,
        'carrying_value_after_cents' => 17000, 'accounting_journal_id' => $sale->id,
    ]);
    overviewPostJournal($viewer, 'OCT-EXPENSE', '2026-10-04', [
        [$expense, 2000, 0], [$cash, 0, 2000],
    ], 'expense');

    activateOverviewBooksForTest();
    Livewire::actingAs($viewer)->test(AccountingOverview::class)
        ->assertSet('fromDate', '2026-10-01')
        ->assertSet('toDate', '2026-10-31')
        ->assertSee('PHP 100.00')
        ->assertSee('PHP 50.00')
        ->assertSee('PHP 50.00')
        ->assertSee('PHP 580.00')
        ->assertSee('PHP 70.00')
        ->assertSee('PHP 170.00')
        ->assertSee('Cash & Bank')
        ->assertSee('OCT-SALE')
        ->assertSee('sale')
        ->assertDontSee('OPEN-OVERVIEW');
    Livewire::actingAs($viewer)->test(AccountingOverview::class)
        ->set('fromDate', '2026-11-01')
        ->set('toDate', '2026-11-30')
        ->assertSee('No posted activity in this period')
        ->assertSee('PHP 0.00')
        ->assertSee('PHP 580.00')
        ->assertSee('PHP 300.00');
    Livewire::actingAs($viewer)->test(AccountingOverview::class)
        ->set('fromDate', '2026-08-01')
        ->set('toDate', '2026-10-31')
        ->assertSee('before approved accounting cutover coverage')
        ->assertDontSee('PHP 0.00');
    Livewire::actingAs($viewer)->test(\App\Livewire\Accounting\FinancialStatements::class)
        ->set('fromDate', '2026-10-01')
        ->set('toDate', '2026-10-31')
        ->assertSee('PHP 100.00')
        ->assertSee('PHP 30.00')
        ->assertSee('PHP 20.00')
        ->assertSee('PHP 50.00');
    Livewire::actingAs($viewer)->test(\App\Livewire\Accounting\TrialBalance::class)
        ->set('fromDate', '2026-10-01')
        ->set('toDate', '2026-10-31')
        ->assertSee('Overview 1000')
        ->assertSee('580.00')
        ->assertSee('Overview 2000')
        ->assertSee('300.00');
});

test('accounting overview excludes drafts and reports truthful unavailable and empty states', function () {
    $viewer = createAccountingOverviewEmployee();
    Livewire::actingAs($viewer)->test(AccountingOverview::class)
        ->assertSee('unavailable')
        ->assertDontSee('PHP 0.00');

    $sales = overviewAccount('4000', 'sales', 'Revenue', 'credit');
    $expense = overviewAccount('6000', 'operating_expense', 'Expense', 'debit');
    $cash = overviewAccount('1000', 'cash', 'Asset', 'debit');
    $equity = overviewAccount('3000', 'capital', 'Equity', 'credit');
    overviewPostJournal($viewer, 'OPEN-EMPTY', '2026-09-01', [
        [$cash, 0, 0], [$equity, 0, 0],
    ], 'opening');
    Livewire::actingAs($viewer)->test(AccountingOverview::class)
        ->assertSee('Production activation unavailable')
        ->assertDontSee('PHP 0.00');
    activateOverviewBooksForTest();
    overviewPostJournal($viewer, 'DRAFT-OVERVIEW', '2026-10-04', [
        [$cash, 90000, 0], [$equity, 0, 90000],
    ], 'manual', 'draft');
    overviewPostJournal($viewer, 'BALANCE-ONLY', '2026-10-05', [
        [$cash, 1000, 0], [$equity, 0, 1000],
    ]);

    Livewire::actingAs($viewer)->test(AccountingOverview::class)
        ->set('fromDate', '2026-10-01')
        ->set('toDate', '2026-10-31')
        ->assertDontSee('No posted activity in this period')
        ->assertSee('PHP 10.00')
        ->assertDontSee('DRAFT-OVERVIEW');
    Livewire::actingAs($viewer)->test(AccountingOverview::class)
        ->set('fromDate', '2026-11-01')
        ->set('toDate', '2026-11-30')
        ->assertSee('No posted activity in this period')
        ->assertSee('PHP 0.00')
        ->assertSee('PHP 10.00');
});

test('accounting overview requires view permission and offers working report destinations', function () {
    $this->get(route('accounting.overview'))->assertRedirect(route('login'));

    $employee = createAccountingOverviewEmployee();
    $this->actingAs($employee)->get(route('accounting.overview'))
        ->assertOk()
        ->assertSee(route('accounting.journal-entry'), false)
        ->assertSee(route('accounting.general-ledger'), false)
        ->assertSee(route('accounting.trial-balance'), false)
        ->assertSee(route('accounting.financial-statements'), false);

    $restricted = grantEmployeeTestPermissions(Employee::create([
        'username' => 'accounting.denied',
        'email' => 'accounting.denied@example.com',
        'password' => Hash::make('accounting-password'),
    ]), []);
    $this->actingAs($restricted)->get(route('accounting.overview'))->assertForbidden();
});

