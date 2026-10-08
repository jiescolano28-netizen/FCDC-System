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
        ->assertDontSee('Cart is empty')
        ->assertSee('All inventory items are above their reorder levels.')
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
        ->assertSee('S-1050')
        ->assertSee('Latest demo sale')
        ->assertSee('These details are not recorded sales')
        ->assertSee('Sales chart uses illustrative sample data');
});

test('dashboard period changes update illustrative chart without treating demo sales as reporting totals', function () {
    $employee = createDashboardEmployee();
    $this->actingAs($employee);

    Livewire::test(DashboardPage::class)
        ->assertSee('Sales this week')
        ->assertSee('₱4,087.35')
        ->call('setSalesPeriod', 'month')
        ->assertSee('Sales this month')
        ->assertSee('Wk 1')
        ->assertSee('₱13,660.65')
        ->call('setSalesPeriod', 'year')
        ->assertSee('Sales this year')
        ->assertSee('Jan')
        ->assertSee('₱85,653.85')
        ->set('salesPeriod', 'unknown')
        ->assertSee('Sales this week');
});
