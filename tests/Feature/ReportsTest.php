<?php

use App\Livewire\Dashboard\ReportsPage;
use App\Livewire\Pos\PointOfSale;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingPeriod;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\PosTransaction;
use App\Services\Accounting\OpeningBooksService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createReportsEmployee(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'reports.manager',
        'email' => 'reports@example.com',
        'password' => Hash::make('reports-password'),
    ]), ['reports.view', 'dashboard.view', 'pos.view', 'pos.checkout']);
}

function createReportsInventory(array $attributes = []): Inventory
{
    return Inventory::create(array_merge([
        'name' => 'Pine board',
        'category' => 'Lumber',
        'qty' => 10,
        'unit' => 'piece',
        'unit_cost' => 5,
        'selling_price' => 20,
        'reorder_level' => 2,
    ], $attributes));
}

test('Reports is a separately routed authenticated Dashboard page', function () {
    $this->get(route('reports'))->assertRedirect(route('login'));

    $this->actingAs(createReportsEmployee())->get(route('reports'))->assertOk()
        ->assertSee('Reports')
        ->assertSee('Dashboard')
        ->assertSee(route('reports'), false)
        ->assertSee('Sales')
        ->assertSee('Inventory')
        ->assertSee('Accounting')
        ->assertSee('VAT Reports')
        ->assertSee('Sales trend')
        ->assertSee('Export CSV')
        ->assertDontSee('id="view-reports"', false);
});

test('Reports separates illustrative trends from persisted transactions and inventory', function () {
    $this->actingAs(createReportsEmployee());
    $board = createReportsInventory();
    $cement = createReportsInventory([
        'name' => 'Cement bag',
        'category' => 'Masonry',
        'qty' => 4,
        'unit' => 'bag',
        'unit_cost' => 8,
    ]);
    setupPosAccountingBooks([$board, $cement]);
    Livewire::test(PointOfSale::class)
        ->call('addToCart', $board->id)
        ->call('changeQuantity', $board->id, 1)
        ->set('amountReceived', '44.80')
        ->call('checkout')
        ->assertHasNoErrors();

    $this->get(route('reports'))->assertOk()
        ->assertSee('Actual completed POS transactions')
        ->assertSee('POS-')
        ->assertSee('₱44.80')
        ->assertSee('Cash')
        ->assertSee('Lumber')
        ->assertSee('"label":"Lumber","total":44.8', false);

    Livewire::test(ReportsPage::class)->set('activeReport', 'inventory')
        ->assertSee('Current inventory value')
        ->assertSee('Lumber')->assertSee('piece')->assertSee('8.00')
        ->assertSee('Masonry')->assertSee('bag')->assertSee('4.00')
        ->assertSee('Inventory is a current snapshot');

    Livewire::test(ReportsPage::class)->set('activeReport', 'vat')
        ->assertSee('Output VAT')
        ->assertSee('saved reworking-cost records')
        ->assertSee('POS-');

    $vatCsvReport = Livewire::test(ReportsPage::class)->set('activeReport', 'vat')
        ->call('exportCsv');
    $vatCsv = base64_decode($vatCsvReport->effects['download']['content']);
    expect($vatCsv)->toContain('Transaction', 'Output VAT', '4.80', 'POS-');

    expect($board->fresh()->qty)->toBe('8.00');
});

test('Reports date presets select actual range-backed sales data', function () {
    $this->actingAs(createReportsEmployee());

    Livewire::test(ReportsPage::class)
        ->assertSee('Sales trend')
        ->call('setPeriod', 'month')
        ->assertSee('Actual completed POS transactions')
        ->call('setPeriod', 'invalid')
        ->assertSee('Actual completed POS transactions');
});

test('Reports shows useful empty states when no inventory or transactions exist', function () {
    $this->actingAs(createReportsEmployee())->get(route('reports'))->assertOk()
        ->assertSee('No completed POS line items in this date range.')
        ->assertSee('No completed POS transactions for this period.')
        ->assertSee('₱0.00');

    Livewire::test(ReportsPage::class)->set('activeReport', 'inventory')
        ->assertSee('No inventory categories are recorded.');
    Livewire::test(ReportsPage::class)->set('activeReport', 'accounting')
        ->assertSee('Accounting report unavailable')
        ->assertSee('Production activation unavailable');
});
test('Reports aggregates completed POS sales daily and monthly from transaction snapshots', function () {
    $this->actingAs(createReportsEmployee());
    $inventory = createReportsInventory();
    $makeTransaction = function (string $number, string $date, string $method, string $itemName, string $category, string $total) use ($inventory): void {
        $subtotal = (float) $total / 1.12;
        $transaction = PosTransaction::create([
            'transaction_number' => $number,
            'subtotal' => $subtotal,
            'vat_rate' => 0.12,
            'vat_amount' => (float) $total - $subtotal,
            'total' => $total,
            'payment_method' => $method,
            'amount_received' => $total,
            'change_due' => 0,
            'status' => 'completed',
            'completed_at' => $date,
        ]);
        $transaction->lines()->create([
            'inventory_id' => $inventory->id,
            'inventory_code' => $inventory->code,
            'item_name' => $itemName,
            'category' => $category,
            'unit' => 'piece',
            'quantity' => 1,
            'selling_price' => $subtotal,
            'unit_cost' => 5,
            'line_subtotal' => $subtotal,
            'vat_amount' => (float) $total - $subtotal,
            'line_total' => $total,
        ]);
    };

    $makeTransaction('POS-DAY-CASH', '2026-10-09 10:00:00', 'cash', 'Pine board snapshot', 'Lumber snapshot', '44.80');
    $makeTransaction('POS-MONTH-CARD', '2026-10-03 10:00:00', 'card', 'Cement snapshot', 'Masonry snapshot', '22.40');
    $makeTransaction('POS-OTHER-MONTH', '2026-09-30 10:00:00', 'bank_transfer', 'Old snapshot', 'Old category', '11.20');

    $reports = Livewire::test(ReportsPage::class)
        ->set('fromDate', '2026-10-09')->set('toDate', '2026-10-09')
        ->assertSee('Completed sales')
        ->assertSee('₱44.80')
        ->assertSee('Pine board snapshot')
        ->assertSee('Lumber snapshot')
        ->assertSee('Cash')
        ->assertDontSee('Cement snapshot')
        ->assertDontSee('Old snapshot');

    $reports->set('fromDate', '2026-10-01')->set('toDate', '2026-10-31')
        ->assertSee('₱67.20')
        ->assertSee('Pine board snapshot')
        ->assertSee('Cement snapshot')
        ->assertSee('Lumber snapshot')
        ->assertSee('Masonry snapshot')
        ->assertSee('Card')
        ->assertDontSee('Old snapshot');
});

test('Reports keeps rendering a usable period for cleared and impossible date filters', function () {
    $this->actingAs(createReportsEmployee());

    Livewire::test(ReportsPage::class)
        ->set('fromDate', '')
        ->assertSee('Completed sales')
        ->set('fromDate', '2026-02-31')
        ->assertSee('Completed sales')
        ->assertSee('Reports');
});

test('Reports CSV exports use filtered persisted sales and inventory records', function () {
    $this->actingAs(createReportsEmployee());
    $inventory = createReportsInventory();
    $date = now('Asia/Manila');
    $transaction = PosTransaction::create([
        'transaction_number' => 'POS-REPORT-CSV-001',
        'subtotal' => '20.00',
        'vat_rate' => '0.12',
        'vat_amount' => '2.40',
        'total' => '22.40',
        'payment_method' => 'cash',
        'amount_received' => '22.40',
        'change_due' => '0.00',
        'status' => 'completed',
        'completed_at' => $date->copy()->utc(),
    ]);
    $transaction->lines()->create([
        'inventory_id' => $inventory->id,
        'inventory_code' => $inventory->code,
        'item_name' => $inventory->name,
        'category' => $inventory->category,
        'unit' => $inventory->unit,
        'quantity' => 1,
        'selling_price' => 20,
        'unit_cost' => 5,
        'line_subtotal' => 20,
        'vat_amount' => 2.40,
        'line_total' => 22.40,
    ]);

    $reports = Livewire::test(ReportsPage::class)
        ->set('fromDate', $date->toDateString())->set('toDate', $date->toDateString())
        ->call('exportCsv')
        ->assertFileDownloaded('fcdc-sales-report-'.$date->toDateString().'-to-'.$date->toDateString().'.csv');
    $salesCsv = base64_decode($reports->effects['download']['content']);
    expect($salesCsv)->toContain('POS-REPORT-CSV-001', 'completed', '22.40');

    $inventoryReport = Livewire::test(ReportsPage::class)->set('activeReport', 'inventory')
        ->call('exportCsv')
        ->assertFileDownloaded('fcdc-inventory-report-'.$date->copy()->startOfMonth()->toDateString().'-to-'.$date->toDateString().'.csv');
    $inventoryCsv = base64_decode($inventoryReport->effects['download']['content']);
    expect($inventoryCsv)->toContain($inventory->code, 'Pine board', 'Lumber', 'In Stock');
});

test('Accounting report calculations use only covered posted approved income and expense entries', function () {
    $this->travelTo(now('Asia/Manila')->setDate(2026, 10, 29));
    $employee = createReportsEmployee();
    $revenueAccount = AccountingAccount::create([
        'code' => 'RPT-4000', 'name' => 'Report Sales', 'type' => 'Revenue', 'classification' => 'sales',
        'normal_balance' => 'credit', 'is_active' => true, 'approved_at' => now(),
    ]);
    $expenseAccount = AccountingAccount::create([
        'code' => 'RPT-6000', 'name' => 'Report Operating Expense', 'type' => 'Expense', 'classification' => 'operating_expense',
        'normal_balance' => 'debit', 'is_active' => true, 'approved_at' => now(),
    ]);
    $period = AccountingPostingPeriod::firstOrCreateForDate('2026-10-15');
    AccountingJournal::create([
        'book_key' => 'FCDC', 'reference' => 'RPT-OPENING', 'source_type' => 'opening', 'source_id' => 'FCDC',
        'accounting_date' => '2026-01-01', 'posting_period_id' => $period->id, 'description' => 'Test cutover',
        'status' => 'posted', 'prepared_by' => $employee->id,
    ]);
    $journal = AccountingJournal::create([
        'book_key' => 'FCDC', 'reference' => 'RPT-POSTED-001', 'source_type' => 'manual_adjustment', 'source_id' => 'RPT-POSTED-001',
        'accounting_date' => '2026-10-15', 'posting_period_id' => $period->id, 'description' => 'Report activity',
        'status' => 'draft', 'prepared_by' => $employee->id,
    ]);
    $journal->lines()->create(['accounting_account_id' => $revenueAccount->id, 'debit_cents' => 0, 'credit_cents' => 100000]);
    $journal->lines()->create(['accounting_account_id' => $expenseAccount->id, 'debit_cents' => 25000, 'credit_cents' => 0]);
    $journal->forceFill(['status' => 'posted', 'posted_at' => now(), 'posted_by' => $employee->id])->save();

    $readiness = Mockery::mock(OpeningBooksService::class);
    $readiness->shouldReceive('readiness')->andReturn(['production_activated' => true, 'production' => 'Production activated']);
    app()->instance(OpeningBooksService::class, $readiness);

    $method = new ReflectionMethod(ReportsPage::class, 'accountingReport');
    $report = $method->invoke(new ReportsPage, '2026-10-01', '2026-10-31');
    expect($report['available'])->toBeTrue()
        ->and($report['revenue'])->toEqual(1000)
        ->and($report['expenses'])->toEqual(250)
        ->and($report['net'])->toEqual(750)
        ->and($report['lines'])->toHaveCount(2);
});
