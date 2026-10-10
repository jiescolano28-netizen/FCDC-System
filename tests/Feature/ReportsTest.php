<?php

use App\Livewire\Dashboard\ReportsPage;
use App\Livewire\Pos\PointOfSale;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\PosTransaction;
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
        ->set('reportDate', '2026-10-09')
        ->assertSee('Completed POS sales')
        ->assertSee('₱44.80')
        ->assertSee('Pine board snapshot')
        ->assertSee('Lumber snapshot')
        ->assertSee('Cash')
        ->assertDontSee('Cement snapshot')
        ->assertDontSee('Old snapshot');

    $reports->set('reportPeriod', 'monthly')
        ->assertSee('₱67.20')
        ->assertSee('Pine board snapshot')
        ->assertSee('Cement snapshot')
        ->assertSee('Lumber snapshot')
        ->assertSee('Masonry snapshot')
        ->assertSee('Card')
        ->assertDontSee('Old snapshot');
});

test('Reports keeps rendering a usable period for cleared and impossible report dates', function () {
    $this->actingAs(createReportsEmployee());

    Livewire::test(ReportsPage::class)
        ->set('reportDate', '')
        ->assertHasErrors('reportDate')
        ->assertSee('Completed POS sales')
        ->set('reportDate', '2026-02-31')
        ->assertHasErrors('reportDate')
        ->assertDontSee('March 3, 2026')
        ->assertSee('Completed POS sales');
});
