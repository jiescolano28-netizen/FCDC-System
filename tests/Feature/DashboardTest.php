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

test('dashboard route serves only the authenticated inventory dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));

    $employee = createDashboardEmployee();
    $this->actingAs($employee)->get(route('dashboard'))->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('dashboard-page', false)
        ->assertSee(route('pos'), false)
        ->assertSee('No inventory materials have been added yet.')
        ->assertSee('Today')
        ->assertSee('This Week')
        ->assertSee('This Month')
        ->assertSee('This Year')
        ->assertSee('Custom Date Range')
        ->assertSee('Sales performance')
        ->assertSee('Revenue vs. expenses')
        ->assertSee('VAT collected')
        ->assertDontSee('Demo sales');
});
test('dashboard shows persisted inventory summaries, stock boundaries, and recent materials without demo business figures', function () {
    $employee = createDashboardEmployee();
    $this->actingAs($employee);

    createDashboardInventory([
        'name' => 'Pine board',
        'category' => 'Lumber',
        'qty' => 10,
        'unit_cost' => 5,
        'reorder_level' => 8,
        'created_at' => now()->subDay(),
    ]);
    createDashboardInventory([
        'name' => 'Cement bag',
        'category' => 'Masonry',
        'qty' => 1,
        'unit_cost' => 8,
        'reorder_level' => 1,
        'created_at' => now(),
    ]);
    createDashboardInventory([
        'name' => 'Steel rod',
        'category' => 'Metal',
        'qty' => 0,
        'unit_cost' => 12,
        'reorder_level' => 5,
        'created_at' => now()->subHours(2),
    ]);
    createDashboardInventory([
        'name' => 'Tile',
        'category' => 'Finishes',
        'qty' => -2,
        'unit_cost' => 3,
        'reorder_level' => 0,
        'created_at' => now()->subHours(3),
    ]);
    Inventory::where('name', 'Pine board')->update(['created_at' => now()->subDay()]);
    Inventory::where('name', 'Cement bag')->update(['created_at' => now()]);
    Inventory::where('name', 'Steel rod')->update(['created_at' => now()->subHours(2)]);
    Inventory::where('name', 'Tile')->update(['created_at' => now()->subHours(3)]);

    Livewire::test(DashboardPage::class)
        ->assertSee('4 tracked materials')
        ->assertSee('₱52.00')
        ->assertSee('Stock alerts')
        ->assertSee('1')
        ->assertSee('Pine board')
        ->assertSee('Cement bag')
        ->assertSee('Steel rod')
        ->assertSee('Tile')
        ->assertSee('2 items currently out of stock.')
        ->assertSee('Recently added materials')
        ->assertSeeInOrder(['Cement bag', 'Steel rod', 'Tile', 'Pine board'])
        ->assertSee('Inventory value')
        ->assertSee('"label":"Lumber","total":50', false)
        ->assertSee('"label":"Masonry","total":8', false)
        ->assertSee('Output VAT');
});

test('dashboard shows useful empty states when inventory and low-stock matches are absent', function () {
    $this->actingAs(createDashboardEmployee());

    Livewire::test(DashboardPage::class)
        ->assertSee('0 tracked materials')
        ->assertSee('₱0.00')
        ->assertSee('No inventory materials have been added yet.')
        ->assertSee('No low-stock items need attention.')
        ->assertSee('No inventory categories to chart yet.')
        ->assertSee('0');
});

test('dashboard date presets and custom date range are interactive', function () {
    $this->actingAs(createDashboardEmployee());
    $dashboard = Livewire::test(DashboardPage::class)->call('setPeriod', 'today');

    expect($dashboard->get('fromDate'))->toBe(now('Asia/Manila')->toDateString())
        ->and($dashboard->get('toDate'))->toBe(now('Asia/Manila')->toDateString());

    $dashboard->set('fromDate', '2026-01-05')->set('toDate', '2026-01-12');
    expect($dashboard->get('fromDate'))->toBe('2026-01-05')
        ->and($dashboard->get('toDate'))->toBe('2026-01-12');
});
