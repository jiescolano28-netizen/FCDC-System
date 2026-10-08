<?php

use App\Livewire\Dashboard\DashboardPage;
use App\Models\Employee;
use App\Models\Inventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createDashboardEmployee(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'dashboard.manager',
        'email' => 'dashboard@example.com',
        'password' => Hash::make('dashboard-password'),
    ]), ['dashboard.view', 'pos.view']);
}

function createDashboardInventory(array $attributes = []): Inventory
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

test('dashboard route serves only the authenticated dashboard Livewire page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));

    $employee = createDashboardEmployee();
    $this->actingAs($employee)->get(route('dashboard'))->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('dashboard-page', false)
        ->assertSee(route('pos'), false)
        ->assertSee('Nothing to show')
        ->assertDontSee('View sale details')
        ->assertSee('No completed demo sales in this session.');
});

test('dashboard metrics use persisted inventory and show session demo sales separately', function () {
    $employee = createDashboardEmployee();
    $this->actingAs($employee);
    createDashboardInventory();
    createDashboardInventory([
        'name' => 'Cement bag',
        'category' => 'Masonry',
        'qty' => 1,
        'unit_cost' => 8,
        'reorder_level' => 1,
    ]);
    session()->put('demo.pos.employee.'.$employee->id.'.sales', [[
        'id' => 'S-1050',
        'date' => '2026-10-04',
        'items' => 2,
        'total' => 44.8,
        'lines' => [['quantity' => 2, 'name' => 'Pine board', 'sellingPrice' => 20]],
    ]]);

    Livewire::test(DashboardPage::class)
        ->assertSee('₱58.00')
        ->assertSee('2 tracked materials')
        ->assertSee('1 item needs reordering')
        ->assertSee('₱44.80')
        ->assertSee('S-1050')
        ->assertSee('Latest inventory item')
        ->assertSee('Cement bag')
        ->assertSee('Masonry · 1.00 piece')
        ->assertSee('Session-only demo checkouts, grouped by checkout date');
});

test('dashboard chart aggregates current employee demo sales by selected period', function () {
    $employee = createDashboardEmployee();
    $this->actingAs($employee)->travelTo(now()->setDate(2026, 10, 8)->startOfDay());
    session()->put('demo.pos.employee.'.$employee->id.'.sales', [
        ['id' => 'S-1052', 'date' => '2026-10-08', 'total' => 40.25, 'items' => 1, 'lines' => []],
        ['id' => 'S-1051', 'date' => '2026-10-05', 'total' => 100.50, 'items' => 2, 'lines' => []],
        ['id' => 'S-1050', 'date' => '2026-10-04', 'total' => 75.00, 'items' => 1, 'lines' => []],
    ]);

    Livewire::test(DashboardPage::class)
        ->assertSee('Demo sales this week')
        ->assertSee('"label":"Mon","total":100.5', false)
        ->assertSee('"label":"Thu","total":40.25', false)
        ->assertDontSee('"label":"Sun","total":75', false)
        ->call('setSalesPeriod', 'month')
        ->assertSee('Demo sales this month')
        ->assertSee('"label":"Wk 1","total":175.5', false)
        ->assertSee('"label":"Wk 2","total":40.25', false)
        ->call('setSalesPeriod', 'year')
        ->assertSee('Demo sales this year')
        ->assertSee('"label":"Oct","total":215.75', false)
        ->set('salesPeriod', 'unknown')
        ->assertSee('Demo sales this week')
        ->assertSee('"label":"Mon","total":100.5', false);
});
