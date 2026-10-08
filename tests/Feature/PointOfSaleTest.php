<?php

use App\Livewire\Pos\PointOfSale;
use App\Livewire\Settings\SettingsPage;
use App\Models\Employee;
use App\Models\Inventory;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class)->beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

function createPosEmployee(array $overrides = []): Employee
{
    return grantEmployeeTestPermissions(Employee::create(array_merge([
        'username' => 'pos.cashier',
        'email' => 'pos@example.com',
        'password' => Hash::make('pos-password'),
    ], $overrides)), ['pos.view', 'pos.checkout', 'settings.view', 'settings.update', 'dashboard.view']);
}

function createPosInventory(array $overrides = []): Inventory
{
    return Inventory::create(array_merge([
        'name' => 'Pine board',
        'category' => 'Lumber',
        'qty' => 10,
        'unit' => 'piece',
        'unit_cost' => 5,
        'selling_price' => 20,
        'reorder_level' => 2,
    ], $overrides));
}

test('POS is an authenticated named Livewire page in the shared shell', function () {
    $this->get(route('pos'))->assertRedirect(route('login'));

    $employee = createPosEmployee();

    $this->actingAs($employee)->get(route('pos'))->assertOk()
        ->assertSee('Point of sale')
        ->assertSee('Temporary demonstration activity', false)
        ->assertSee(route('settings'), false)
        ->assertSee(route('pos'), false);

    $this->get(route('dashboard'))->assertOk()
        ->assertSee(route('pos'), false)
        ->assertDontSee('id="view-pos"', false);
});

test('cashier can search, edit a bounded cart, and keep it after navigation and refresh', function () {
    $this->actingAs(createPosEmployee());
    $board = createPosInventory();
    createPosInventory(['name' => 'Cement bag', 'category' => 'Concrete']);

    $component = Livewire::test(PointOfSale::class)
        ->set('search', 'pine')
        ->assertSee('Pine board')
        ->assertDontSee('Cement bag')
        ->call('addToCart', $board->id)
        ->call('changeQuantity', $board->id, 1)
        ->assertSee('Subtotal');

    foreach (range(1, 8) as $step) {
        $component->call('changeQuantity', $board->id, 1);
    }

    $component->call('changeQuantity', $board->id, 1)->assertHasNoErrors();

    expect(session('demo.pos.employee.'.auth()->id().'.cart')[$board->id]['quantity'])->toBe(10);

    $this->get(route('settings'))->assertOk();
    $this->get(route('pos'))->assertOk()->assertSee('Pine board');

    Livewire::test(PointOfSale::class)
        ->call('changeQuantity', $board->id, -1)
        ->call('removeFromCart', $board->id)
        ->assertSee('Cart is empty');
});

test('fractional stock below one unit cannot be over-added to the integer POS cart', function () {
    $this->actingAs(createPosEmployee());
    $board = createPosInventory(['qty' => 0.5]);

    Livewire::test(PointOfSale::class)
        ->call('addToCart', $board->id)
        ->call('checkout')
        ->assertSee('No matching in-stock materials.')
        ->assertSee('Cart is empty');

    expect(session('demo.pos.employee.'.auth()->id().'.cart'))->toBe([])
        ->and($board->fresh()->qty)->toBe('0.50');
});

test('demo checkout preserves sale details in session without changing persisted stock', function () {
    $employee = createPosEmployee();
    $this->actingAs($employee);
    $board = createPosInventory();
    Livewire::test(SettingsPage::class)
        ->set('companyName', 'North Shore Materials')
        ->set('address', '18 Harbor Road')
        ->set('phone', '555-0142');


    Livewire::test(PointOfSale::class)
        ->call('addToCart', $board->id)
        ->call('changeQuantity', $board->id, 1)
        ->call('checkout')
        ->assertSee('Demo sale S-1050 completed')
        ->assertSee('demonstration activity, not a recorded sale')
        ->assertSee('Pine board')
        ->assertSee('North Shore Materials')
        ->assertSee('18 Harbor Road')
        ->assertSee('555-0142')
        ->assertSee('₱44.80');

    expect($board->fresh()->qty)->toBe('10.00')
        ->and(session('demo.pos.employee.'.$employee->id.'.stock')[$board->id])->toBe(2)
        ->and(session('demo.pos.employee.'.$employee->id.'.sales')[0]['total'])->toBe(44.8);

    $this->get(route('pos'))->assertOk()->assertSee('8.00 piece left')->assertSee('S-1050');
    $this->get(route('dashboard'))->assertOk()
        ->assertSee('Completed demonstration sales')
        ->assertSee('S-1050')
        ->assertSee('2 × Pine board')
        ->assertSee('₱50.00')
        ->assertSee('"label":"Lumber","total":50', false);
});

test('checkout cannot exceed simulated stock and logout clears POS state for the next employee', function () {
    $employee = createPosEmployee();
    $otherEmployee = createPosEmployee(['username' => 'other.cashier', 'email' => 'other@example.com']);
    $this->actingAs($employee);
    $board = createPosInventory(['qty' => 1]);

    Livewire::test(PointOfSale::class)
        ->call('addToCart', $board->id)
        ->call('changeQuantity', $board->id, 1)
        ->call('checkout')
        ->assertSee('Demo sale S-1050 completed');

    $this->post(route('logout'))->assertRedirect(route('login'));
    $this->post(route('login.submit'), [
        'email' => $otherEmployee->email,
        'password' => 'pos-password',
    ])->assertRedirect(route('dashboard'));

    $this->get(route('pos'))->assertOk()
        ->assertSee('Cart is empty')
        ->assertDontSee('S-1050');

    expect($board->fresh()->qty)->toBe('1.00');
});
