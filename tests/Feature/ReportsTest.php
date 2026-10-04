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
    return Employee::create([
        'username' => 'reports.manager',
        'email' => 'reports@example.com',
        'password' => Hash::make('reports-password'),
    ]);
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

test('Reports separates illustrative sales from session demo sales and persisted inventory', function () {
    $employee = createReportsEmployee();
    $this->actingAs($employee);
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
        ->call('checkout');

    $response = $this->get(route('reports'));
    $response->assertOk()
        ->assertSee('Fixed illustrative series')
        ->assertSee('not recorded sales')
        ->assertSee('S-1050')
        ->assertSee('₱44.80')
        ->assertSee('Card')
        ->assertSee('Lumber')
        ->assertSee('Masonry')
        ->assertSee('Lumber · piece: 10.00')
        ->assertSee('Masonry · bag: 4.00')
        ->assertSee('"label":"Lumber","total":50', false)
        ->assertSee('"label":"Masonry","total":32', false);

    expect($board->fresh()->qty)->toBe('10.00');
});

test('Reports period selection updates only the illustrative sales series', function () {
    $employee = createReportsEmployee();
    $this->actingAs($employee);
    session()->put('demo.pos.employee.'.$employee->id.'.sales', [[
        'id' => 'S-1050',
        'date' => '2026-10-04',
        'items' => 1,
        'total' => 22.4,
        'method' => 'Card',
        'lines' => [],
    ]]);

    Livewire::test(ReportsPage::class)
        ->assertSee('Sales trend')
        ->assertSee('S-1050')
        ->call('setSalesPeriod', 'month')
        ->assertSee('Wk 1')
        ->assertSee('S-1050')
        ->call('setSalesPeriod', 'invalid')
        ->assertSee('Wk 1');
});

test('Reports shows useful empty states when no inventory or demo sales exist', function () {
    $this->actingAs(createReportsEmployee())->get(route('reports'))->assertOk()
        ->assertSee('No inventory categories to chart yet.')
        ->assertSee('No completed demo sales in this session.')
        ->assertSee('₱0.00');
});
