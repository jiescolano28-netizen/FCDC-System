<?php

use App\Livewire\Dashboard\ReportsPage;
use App\Livewire\Pos\PointOfSale;
use App\Models\Employee;
use App\Models\Inventory;
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
        ->assertSee('Sales trend')
        ->assertSee('Inventory value by category')
        ->assertSee('Recent sales')
        ->assertDontSee('id="view-reports"', false);
});

test('Reports separates illustrative trends from persisted transactions and inventory', function () {
    $this->actingAs(createReportsEmployee());
    $board = createReportsInventory();
    createReportsInventory([
        'name' => 'Cement bag',
        'category' => 'Masonry',
        'qty' => 4,
        'unit' => 'bag',
        'unit_cost' => 8,
    ]);
    Livewire::test(PointOfSale::class)
        ->call('addToCart', $board->id)
        ->call('changeQuantity', $board->id, 1)
        ->set('amountReceived', '44.80')
        ->call('checkout')
        ->assertHasNoErrors();

    $this->get(route('reports'))->assertOk()
        ->assertSee('Fixed illustrative series')
        ->assertSee('POS-')
        ->assertSee('₱44.80')
        ->assertSee('Cash')
        ->assertSee('Lumber')
        ->assertSee('Masonry')
        ->assertSee('Lumber · piece: 8.00')
        ->assertSee('Masonry · bag: 4.00')
        ->assertSee('"label":"Lumber","total":40', false)
        ->assertSee('"label":"Masonry","total":32', false);

    expect($board->fresh()->qty)->toBe('8.00');
});

test('Reports period selection updates only the illustrative sales series', function () {
    $this->actingAs(createReportsEmployee());

    Livewire::test(ReportsPage::class)
        ->assertSee('Sales trend')
        ->call('setSalesPeriod', 'month')
        ->assertSee('Wk 1')
        ->call('setSalesPeriod', 'invalid')
        ->assertSee('Wk 1');
});

test('Reports shows useful empty states when no inventory or transactions exist', function () {
    $this->actingAs(createReportsEmployee())->get(route('reports'))->assertOk()
        ->assertSee('No inventory categories to chart yet.')
        ->assertSee('No completed transactions.')
        ->assertSee('₱0.00');
});
