<?php

use App\Livewire\Pos\PointOfSale;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingMapping;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\PosTransaction;
use App\Models\StockMovement;
use App\Services\Accounting\PosSalePostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function posAccountingEmployee(array $permissions = ['pos.checkout']): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'pos-accounting.'.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => Hash::make('secret-password'),
    ]), $permissions);
}

test('a completed sale posts recorded cash, sales and VAT plus frozen moving-average COGS atomically', function () {
    $employee = posAccountingEmployee();
    $this->actingAs($employee);
    $item = Inventory::create([
        'name' => 'Valued board', 'category' => 'Lumber', 'qty' => '10.00', 'unit' => 'piece',
        'unit_cost' => '999.00', 'selling_price' => '180.00', 'reorder_level' => '0',
    ]);
    $accounts = setupPosAccountingBooks([$item], [$item->id => '500.00']);

    Livewire::test(PointOfSale::class)
        ->call('addToCart', $item->id)
        ->set('amountReceived', '201.60')
        ->call('checkout')
        ->assertHasNoErrors();

    $sale = PosTransaction::query()->with('lines')->sole();
    $journal = AccountingJournal::query()->where('source_type', 'pos_sale')->where('source_id', (string) $sale->id)->with('lines')->sole();
    $lines = $journal->lines->keyBy('accounting_account_id');
    $movement = StockMovement::query()->where('reference', $sale->transaction_number)->sole();

    expect($sale->status)->toBe('completed')
        ->and($sale->subtotal)->toBe('180.00')
        ->and($sale->vat_amount)->toBe('21.60')
        ->and($sale->total)->toBe('201.60')
        ->and($journal->status)->toBe('posted')
        ->and($lines[$accounts['cash']->id]->debit_cents)->toBe(20160)
        ->and($lines[$accounts['sales']->id]->credit_cents)->toBe(18000)
        ->and($lines[$accounts['output_vat']->id]->credit_cents)->toBe(2160)
        ->and($lines[$accounts['cogs']->id]->debit_cents)->toBe(5000)
        ->and($lines[$accounts['inventory']->id]->credit_cents)->toBe(5000)
        ->and($movement->value_cents)->toBe(-5000)
        ->and($movement->carrying_value_after_cents)->toBe(45000)
        ->and($movement->accounting_journal_id)->toBe($journal->id)
        ->and($item->fresh()->qty)->toBe('9.00')
        ->and($item->fresh()->carrying_value_cents)->toBe(45000)
        ->and($sale->lines->first()->unit_cost)->toBe('50.00');
});

test('a sale preserves exact rounded line COGS when unit-cost display rounds', function () {
    $employee = posAccountingEmployee();
    $this->actingAs($employee);
    $item = Inventory::create([
        'name' => 'Low-cost fastener', 'category' => 'Hardware', 'qty' => '3.00', 'unit' => 'piece',
        'unit_cost' => '0.33', 'selling_price' => '180.00', 'reorder_level' => '0',
    ]);
    $accounts = setupPosAccountingBooks([$item], [$item->id => '1.00']);

    Livewire::test(PointOfSale::class)
        ->call('addToCart', $item->id)
        ->call('setQuantity', $item->id, 2)
        ->set('amountReceived', '403.20')
        ->call('checkout')
        ->assertHasNoErrors();

    $sale = PosTransaction::query()->with('lines')->sole();
    $journal = AccountingJournal::query()->where('source_type', 'pos_sale')->where('source_id', (string) $sale->id)->with('lines')->sole();
    $movement = StockMovement::query()->where('reference', $sale->transaction_number)->sole();

    expect($sale->lines->first()->unit_cost)->toBe('0.33')
        ->and($sale->lines->first()->line_cost_cents)->toBe(67)
        ->and($journal->lines->firstWhere('accounting_account_id', $accounts['cogs']->id)->debit_cents)->toBe(67)
        ->and($journal->lines->firstWhere('accounting_account_id', $accounts['inventory']->id)->credit_cents)->toBe(67)
        ->and($movement->value_cents)->toBe(-67)
        ->and($movement->carrying_value_after_cents)->toBe(33)
        ->and($item->fresh()->qty)->toBe('1.00')
        ->and($item->fresh()->carrying_value_cents)->toBe(33);
});

test('card and confirmed bank-transfer proceeds use their own accounts and later cost edits do not rewrite prior COGS', function () {
    $employee = posAccountingEmployee();
    $this->actingAs($employee);
    $item = Inventory::create([
        'name' => 'Valued board', 'category' => 'Lumber', 'qty' => '10.00', 'unit' => 'piece',
        'unit_cost' => '999.00', 'selling_price' => '180.00', 'reorder_level' => '0',
    ]);
    $accounts = setupPosAccountingBooks([$item], [$item->id => '500.00']);

    Livewire::test(PointOfSale::class)
        ->call('addToCart', $item->id)
        ->set('paymentMethod', 'card')
        ->set('paymentReference', 'CARD-100')
        ->set('amountReceived', '201.60')
        ->call('checkout')
        ->assertHasNoErrors();
    $firstSale = PosTransaction::query()->orderBy('id')->firstOrFail();
    $firstJournal = AccountingJournal::query()->where('source_type', 'pos_sale')->where('source_id', (string) $firstSale->id)->with('lines')->sole();
    $firstCogs = $firstJournal->lines->firstWhere('accounting_account_id', $accounts['cogs']->id)->debit_cents;

    DB::table('inventories')->where('id', $item->id)->update(['unit_cost' => '777.00']);
    Livewire::test(PointOfSale::class)
        ->call('addToCart', $item->id)
        ->set('paymentMethod', 'bank_transfer')
        ->set('paymentReference', 'BANK-200')
        ->set('amountReceived', '201.60')
        ->call('checkout')
        ->assertHasNoErrors();

    $secondSale = PosTransaction::query()->orderByDesc('id')->firstOrFail();
    $secondJournal = AccountingJournal::query()->where('source_type', 'pos_sale')->where('source_id', (string) $secondSale->id)->with('lines')->sole();
    $secondLines = $secondJournal->lines->keyBy('accounting_account_id');
    $firstJournal->refresh()->load('lines');

    expect($firstCogs)->toBe(5000)
        ->and($firstJournal->lines->firstWhere('accounting_account_id', $accounts['cogs']->id)->debit_cents)->toBe(5000)
        ->and($firstJournal->lines->firstWhere('accounting_account_id', $accounts['card_clearing']->id)->debit_cents)->toBe(20160)
        ->and($secondLines->get($accounts['cash']->id))->toBeNull()
        ->and($secondLines->get($accounts['card_clearing']->id))->toBeNull()
        ->and($secondLines[$accounts['cogs']->id]->debit_cents)->toBe(5000)
        ->and($item->fresh()->qty)->toBe('8.00')
        ->and($item->fresh()->carrying_value_cents)->toBe(40000);
});

test('a missing posting mapping rolls back the completed sale and stock effects', function () {
    $employee = posAccountingEmployee();
    $this->actingAs($employee);
    $item = Inventory::create([
        'name' => 'Valued board', 'category' => 'Lumber', 'qty' => '10.00', 'unit' => 'piece',
        'unit_cost' => '50.00', 'selling_price' => '180.00', 'reorder_level' => '0',
    ]);
    $accounts = setupPosAccountingBooks([$item], [$item->id => '500.00']);
    AccountingPostingMapping::query()->where('source', 'output_vat')->delete();

    Livewire::test(PointOfSale::class)
        ->call('addToCart', $item->id)
        ->set('amountReceived', '201.60')
        ->call('checkout')
        ->assertHasErrors('mapping');
    expect(PosTransaction::query()->count())->toBe(0)
        ->and(StockMovement::query()->where('inventory_id', $item->id)->where('reason_category', 'sale')->count())->toBe(0)
        ->and($item->fresh()->qty)->toBe('10.00');

});

test('a POS sale cannot complete twice under the same source identity', function () {
    $employee = posAccountingEmployee();
    $this->actingAs($employee);
    $item = Inventory::create([
        'name' => 'Valued board', 'category' => 'Lumber', 'qty' => '10.00', 'unit' => 'piece',
        'unit_cost' => '50.00', 'selling_price' => '180.00', 'reorder_level' => '0',
    ]);
    setupPosAccountingBooks([$item], [$item->id => '500.00']);
    Livewire::test(PointOfSale::class)
        ->call('addToCart', $item->id)
        ->set('amountReceived', '201.60')
        ->call('checkout')
        ->assertHasNoErrors();
    $sale = PosTransaction::query()->with('lines')->sole();

    expect(fn () => app(PosSalePostingService::class)->post($sale, [[
        'inventory' => $item->fresh(),
        'quantity' => 1.0,
    ]], $employee->id))->toThrow(ValidationException::class);

    expect(AccountingJournal::query()->where('source_type', 'pos_sale')->count())->toBe(1)
        ->and(StockMovement::query()->where('inventory_id', $item->id)->where('reason_category', 'sale')->count())->toBe(1)
        ->and($item->fresh()->qty)->toBe('9.00');
});

test('a closed accounting period prevents checkout without changing the sale or valued stock', function () {
    $employee = posAccountingEmployee();
    $this->actingAs($employee);
    $item = Inventory::create([
        'name' => 'Valued board', 'category' => 'Lumber', 'qty' => '10.00', 'unit' => 'piece',
        'unit_cost' => '50.00', 'selling_price' => '180.00', 'reorder_level' => '0',
    ]);
    setupPosAccountingBooks([$item], [$item->id => '500.00']);
    DB::table('accounting_posting_periods')->update(['status' => 'closed']);

    Livewire::test(PointOfSale::class)
        ->call('addToCart', $item->id)
        ->set('amountReceived', '201.60')
        ->call('checkout')
        ->assertHasErrors('accounting');

    expect(PosTransaction::query()->count())->toBe(0)
        ->and(StockMovement::query()->where('inventory_id', $item->id)->where('reason_category', 'sale')->count())->toBe(0)
        ->and($item->fresh()->qty)->toBe('10.00')
        ->and($item->fresh()->carrying_value_cents)->toBe(50000);
});
