<?php

use App\Livewire\Pos\PointOfSale;
use App\Livewire\Settings\SettingsPage;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\PosTransaction;
use App\Models\PosVatRecord;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    $this->actingAs(createPosEmployee())->get(route('pos'))->assertOk()
        ->assertSee('Point of sale')
        ->assertSee('Transaction history')
        ->assertSee('Bank transfer')
        ->assertSee(route('settings'), false)
        ->assertSee(route('pos'), false)
        ->assertDontSee('demonstration checkout');

    $this->get(route('dashboard'))->assertOk()
        ->assertSee(route('pos'), false)
        ->assertDontSee('id=\"view-pos\"', false);
});

test('cashier can search by item or category, adjust a bounded decimal cart, and keep it after navigation', function () {
    $this->actingAs(createPosEmployee());
    $board = createPosInventory();
    createPosInventory(['name' => 'Cement bag', 'category' => 'Concrete']);

    $component = Livewire::test(PointOfSale::class)
        ->set('category', 'Concrete')
        ->assertSee('Cement bag')
        ->assertDontSee('Pine board')
        ->set('category', '')
        ->set('search', 'pine')
        ->assertSee('Pine board')
        ->assertDontSee('Cement bag')
        ->call('addToCart', $board->id)
        ->call('addToCart', $board->id)
        ->assertSee('2.00')
        ->call('setQuantity', $board->id, 1.25)
        ->assertSee('1.25');

    expect(session('pos.employee.'.auth()->id().'.cart')[$board->id]['quantity'])->toBe(1.25);

    $this->get(route('settings'))->assertOk();
    $this->get(route('pos'))->assertOk()->assertSee('Pine board')->assertSee('1.25');

    Livewire::test(PointOfSale::class)
        ->call('setQuantity', $board->id, 10.01)
        ->assertHasErrors('cart')
        ->call('removeFromCart', $board->id)
        ->assertSee('Cart is empty');
});

test('fractional stock is available and can be sold at two decimal precision', function () {
    $this->actingAs(createPosEmployee());
    $board = createPosInventory(['qty' => 0.50]);
    setupPosAccountingBooks([$board]);

    Livewire::test(PointOfSale::class)
        ->call('addToCart', $board->id)
        ->call('setQuantity', $board->id, 0.50)
        ->set('amountReceived', '11.20')
        ->call('checkout')
        ->assertHasNoErrors()
        ->assertSee('₱11.20');

    expect($board->fresh()->qty)->toBe('0.00')
        ->and(PosTransaction::query()->sole()->lines()->first()->quantity)->toBe('0.50');
});

test('cash checkout persists VAT, payment, customer, price snapshots, stock movement, history, and receipt', function () {
    $employee = createPosEmployee();
    $this->actingAs($employee);
    $board = createPosInventory();
    setupPosAccountingBooks([$board]);
    Livewire::test(SettingsPage::class)
        ->set('companyName', 'North Shore Materials')
        ->set('address', '18 Harbor Road')
        ->set('phone', '555-0142');

    Livewire::test(PointOfSale::class)
        ->call('addToCart', $board->id)
        ->call('setQuantity', $board->id, 1.25)
        ->set('customerName', 'Walk-in customer')
        ->set('amountReceived', '30.00')
        ->call('checkout')
        ->assertHasNoErrors()
        ->assertSee('POS-')
        ->assertSee('Walk-in customer')
        ->assertSee('₱28.00')
        ->assertSee('₱2.00')
        ->assertSee('View receipt')
        ->call('viewReceipt', PosTransaction::query()->sole()->id)
        ->assertSee('Receipt · Completed transaction')
        ->assertSee('North Shore Materials')
        ->assertSee('18 Harbor Road')
        ->assertSee('555-0142')
        ->assertSee('Pine board × 1.25 piece')
        ->assertSee('₱25.00')
        ->assertSee('₱3.00');

    $transaction = PosTransaction::query()->with('lines')->sole();

    expect($transaction->status)->toBe('completed')
        ->and($transaction->subtotal)->toBe('25.00')
        ->and($transaction->vat_rate)->toBe('0.1200')
        ->and($transaction->vat_amount)->toBe('3.00')
        ->and($transaction->total)->toBe('28.00')
        ->and($transaction->payment_method)->toBe('cash')
        ->and($transaction->amount_received)->toBe('30.00')
        ->and($transaction->change_due)->toBe('2.00')
        ->and($transaction->customer_name)->toBe('Walk-in customer')
        ->and($transaction->lines)->toHaveCount(1)
        ->and($transaction->lines[0]->quantity)->toBe('1.25')
        ->and($transaction->lines[0]->selling_price)->toBe('20.00')
        ->and($transaction->lines[0]->unit_cost)->toBe('5.00')
        ->and($board->fresh()->qty)->toBe('8.75')
        ->and((float) $board->stockMovements()->where('type', 'stock_out')->sum('quantity'))->toBe(-1.25);

    expect(fn () => $transaction->update(['customer_name' => 'Edited customer']))
        ->toThrow(LogicException::class);
    expect(fn () => $transaction->lines[0]->delete())
        ->toThrow(LogicException::class);
    expect(fn () => (new PosTransaction(['status' => 'pending']))->save())
        ->toThrow(LogicException::class);
});

test('POS viewers can open and close receipts without checkout permission', function () {
    $this->actingAs(createPosEmployee());
    $board = createPosInventory();
    setupPosAccountingBooks([$board]);

    Livewire::test(PointOfSale::class)
        ->call('addToCart', $board->id)
        ->set('amountReceived', '22.40')
        ->call('checkout')
        ->assertHasNoErrors();

    $transaction = PosTransaction::query()->sole();
    $viewer = grantEmployeeTestPermissions(Employee::create([
        'username' => 'pos.viewer',
        'email' => 'pos-viewer@example.com',
        'password' => Hash::make('pos-password'),
    ]), ['pos.view']);
    $this->actingAs($viewer);

    Livewire::test(PointOfSale::class)
        ->assertSee('View receipt')
        ->call('viewReceipt', $transaction->id)
        ->assertSee('Receipt · Completed transaction')
        ->call('closeReceipt')
        ->assertDontSee('Receipt · Completed transaction');
});

test('card and bank transfers require exact payment and a reference number', function () {
    $this->actingAs(createPosEmployee());
    $board = createPosInventory(['qty' => 4]);
    setupPosAccountingBooks([$board]);

    Livewire::test(PointOfSale::class)
        ->call('addToCart', $board->id)
        ->set('paymentMethod', 'card')
        ->set('amountReceived', '44.79')
        ->set('paymentReference', 'CARD-1')
        ->call('checkout')
        ->assertHasErrors('amountReceived');

    Livewire::test(PointOfSale::class)
        ->call('addToCart', $board->id)
        ->call('setQuantity', $board->id, 1)
        ->set('paymentMethod', 'card')
        ->set('amountReceived', '22.40')
        ->call('checkout')
        ->assertHasErrors('paymentReference');

    Livewire::test(PointOfSale::class)
        ->call('addToCart', $board->id)
        ->call('setQuantity', $board->id, 1)
        ->set('paymentMethod', 'bank_transfer')
        ->set('amountReceived', '22.40')
        ->set('paymentReference', 'BANK-8734')
        ->call('checkout')
        ->assertHasNoErrors()
        ->assertSee('Bank transfer')
        ->assertSee('BANK-8734');

    $transaction = PosTransaction::query()->sole();
    expect($transaction->payment_method)->toBe('bank_transfer')
        ->and($transaction->payment_reference)->toBe('BANK-8734')
        ->and($transaction->amount_received)->toBe('22.40')
        ->and($transaction->change_due)->toBe('0.00');
});

test('a checkout failure leaves transaction records and stock untouched', function () {
    $this->actingAs(createPosEmployee());
    $board = createPosInventory(['qty' => 1]);

    Livewire::test(PointOfSale::class)
        ->call('addToCart', $board->id)
        ->set('amountReceived', '1.00')
        ->call('checkout')
        ->assertHasErrors('amountReceived');

    expect(PosTransaction::query()->count())->toBe(0)
        ->and($board->fresh()->qty)->toBe('1.00')
        ->and($board->stockMovements()->where('type', 'stock_out')->count())->toBe(0);

    $secondBoard = createPosInventory(['name' => 'Cement bag', 'qty' => 1]);
    Livewire::test(PointOfSale::class)
        ->call('addToCart', $secondBoard->id);
    Inventory::query()->whereKey($secondBoard->id)->update(['qty' => 0]);

    Livewire::test(PointOfSale::class)
        ->set('amountReceived', '44.80')
        ->call('checkout')
        ->assertHasErrors('cart');

    expect(PosTransaction::query()->count())->toBe(0)
        ->and($board->fresh()->qty)->toBe('1.00')
        ->and($board->stockMovements()->where('type', 'stock_out')->count())->toBe(0);
});

test('POS offers only active priced items with positive stock', function () {
    $this->actingAs(createPosEmployee());
    $activeItem = createPosInventory(['name' => 'Active boards']);
    $inactiveItem = createPosInventory(['name' => 'Retired boards', 'status' => 'inactive']);
    createPosInventory(['name' => 'Unpriced boards', 'selling_price' => null]);

    Livewire::test(PointOfSale::class)
        ->assertSee('Active boards')
        ->assertDontSee('Retired boards')
        ->assertDontSee('Unpriced boards');

    expect(Inventory::availableForSale()->pluck('id')->all())
        ->toContain($activeItem->id)
        ->not->toContain($inactiveItem->id);
});
test('cashier checkout creates its linked VAT record without tax-view permission', function () {
    $cashier = grantEmployeeTestPermissions(Employee::create([
        'username' => 'vat-cashier',
        'email' => 'vat-cashier@example.com',
        'password' => Hash::make('pos-password'),
    ]), ['pos.checkout']);
    $this->actingAs($cashier);
    $board = createPosInventory(['selling_price' => 180, 'qty' => 2]);
    setupPosAccountingBooks([$board]);

    Livewire::test(PointOfSale::class)
        ->call('addToCart', $board->id)
        ->set('amountReceived', '201.60')
        ->call('checkout')
        ->assertHasNoErrors();

    $transaction = PosTransaction::query()->sole();
    $record = PosVatRecord::query()->sole();

    expect($record->pos_transaction_id)->toBe($transaction->id)
        ->and($record->taxable_sales)->toBe('180.00')
        ->and($record->vat_rate)->toBe('0.1200')
        ->and($record->output_vat)->toBe('21.60')
        ->and($record->total)->toBe('201.60')
        ->and($board->fresh()->qty)->toBe('1.00')
        ->and($cashier->can('tax.view'))->toBeFalse();
});
test('VAT persistence failure rolls back the sale and stock changes', function () {
    $this->actingAs(createPosEmployee());
    $board = createPosInventory(['selling_price' => 180, 'qty' => 2]);
    DB::unprepared("CREATE TRIGGER reject_pos_vat_record BEFORE INSERT ON pos_vat_records BEGIN SELECT RAISE(ABORT, 'VAT record failure'); END");

    expect(fn () => Livewire::test(PointOfSale::class)
        ->call('addToCart', $board->id)
        ->set('amountReceived', '201.60')
        ->call('checkout'))->toThrow(QueryException::class);

    DB::statement('DROP TRIGGER reject_pos_vat_record');
    expect(PosTransaction::query()->count())->toBe(0)
        ->and(PosVatRecord::query()->count())->toBe(0)
        ->and($board->fresh()->qty)->toBe('2.00')
        ->and($board->stockMovements()->where('type', 'stock_out')->count())->toBe(0);
});
