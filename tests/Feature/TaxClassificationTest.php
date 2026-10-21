<?php

use App\Livewire\Inventory\InventoryManagement;
use App\Livewire\Pos\PointOfSale;
use App\Livewire\TaxCompliance\TaxReport;
use App\Livewire\TaxCompliance\VatRecords;
use App\Livewire\TaxCompliance\VatReturnPreparation;
use App\Livewire\TaxCompliance\VatSummary;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\PosTransaction;
use App\Services\RecordedVatSummary;
use App\Support\TaxClassification;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class)->beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

function createTaxClassificationEmployee(array $permissions = ['pos.view', 'pos.checkout', 'tax.view', 'inventory.view', 'inventory.create']): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'tax.classification.'.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => Hash::make('tax-password'),
    ]), $permissions);
}

function createTaxClassificationItem(array $overrides = []): Inventory
{
    return Inventory::create(array_merge([
        'name' => 'Taxable board',
        'category' => 'Lumber',
        'qty' => 5,
        'unit' => 'piece',
        'unit_cost' => 50,
        'selling_price' => 100,
        'tax_classification' => TaxClassification::VATABLE,
        'reorder_level' => 1,
    ], $overrides));
}

test('POS applies one transaction-level classification and calculates VAT and total automatically', function () {
    $this->actingAs(createTaxClassificationEmployee());
    $item = createTaxClassificationItem();
    setupPosAccountingBooks([$item]);

    $component = Livewire::test(PointOfSale::class)
        ->call('addToCart', $item->id)
        ->assertSee('One classification applies to every item in this sale.')
        ->assertSee('VATable Sales')
        ->assertDontSee('setTaxClassification')
        ->assertSet('saleTaxClassification', TaxClassification::VATABLE);

    expect($component->viewData('cartTaxClassification'))->toBe(TaxClassification::VATABLE)
        ->and($component->viewData('vatRate'))->toBe(0.12)
        ->and($component->viewData('vat'))->toBe(12)
        ->and($component->viewData('total'))->toBe(112);

    $component
        ->set('amountReceived', '112.00')
        ->call('checkout')
        ->assertHasNoErrors();

    $transaction = PosTransaction::query()->with(['lines', 'vatRecord'])->sole();

    expect($transaction->tax_classification)->toBe(TaxClassification::VATABLE)
        ->and($transaction->subtotal)->toBe('100.00')
        ->and($transaction->vat_rate)->toBe('0.1200')
        ->and($transaction->vat_amount)->toBe('12.00')
        ->and($transaction->total)->toBe('112.00')
        ->and($transaction->lines)->toHaveCount(1)
        ->and($transaction->lines[0]->tax_classification)->toBe(TaxClassification::VATABLE)
        ->and($transaction->vatRecord->tax_classification)->toBe(TaxClassification::VATABLE)
        ->and($transaction->vatRecord->taxable_sales)->toBe('100.00')
        ->and($transaction->vatRecord->output_vat)->toBe('12.00')
        ->and($transaction->vatRecord->total)->toBe('112.00');

    Livewire::test(PointOfSale::class)
        ->call('viewReceipt', $transaction->id)
        ->assertSee('Receipt · Completed transaction')
        ->assertSee('Tax classification')
        ->assertSee('VATable Sales');
});

test('POS recalculates VAT and total from the selected transaction classification', function () {
    $this->actingAs(createTaxClassificationEmployee());
    $vatable = createTaxClassificationItem(['selling_price' => 100]);
    $zeroRated = createTaxClassificationItem([
        'name' => 'Zero-rated board', 'selling_price' => 50, 'unit_cost' => 20,
        'tax_classification' => TaxClassification::ZERO_RATED,
    ]);

    $component = Livewire::test(PointOfSale::class)
        ->call('setSaleTaxClassification', TaxClassification::ZERO_RATED)
        ->call('addToCart', $zeroRated->id)
        ->assertSet('saleTaxClassification', TaxClassification::ZERO_RATED);

    expect($component->viewData('cartTaxClassification'))->toBe(TaxClassification::ZERO_RATED)
        ->and($component->viewData('vat'))->toBe(0)
        ->and($component->viewData('total'))->toBe(50);

    $component
        ->call('setSaleTaxClassification', TaxClassification::VATABLE)
        ->assertHasNoErrors()
        ->assertSet('saleTaxClassification', TaxClassification::VATABLE);

    expect($component->viewData('vat'))->toBe(6)
        ->and($component->viewData('total'))->toBe(56);

    $component
        ->call('setSaleTaxClassification', TaxClassification::VAT_EXEMPT)
        ->assertHasNoErrors();

    expect($component->get('saleTaxClassification'))->toBe(TaxClassification::VAT_EXEMPT)
        ->and($component->viewData('vat'))->toBe(0)
        ->and($component->viewData('total'))->toBe(50);

    $component
        ->call('removeFromCart', $zeroRated->id)
        ->call('setSaleTaxClassification', TaxClassification::VATABLE)
        ->call('addToCart', $vatable->id);

    expect($component->viewData('cartTaxClassification'))->toBe(TaxClassification::VATABLE)
        ->and($component->viewData('vat'))->toBe(12)
        ->and($component->viewData('total'))->toBe(112);
});

test('POS applies the transaction classification to every material regardless of its saved classification', function () {
    $employee = createTaxClassificationEmployee();
    $this->actingAs($employee);
    $vatable = createTaxClassificationItem(['name' => 'Taxable board']);
    $zeroRated = createTaxClassificationItem([
        'name' => 'Zero-rated board', 'tax_classification' => TaxClassification::ZERO_RATED,
    ]);

    $component = Livewire::test(PointOfSale::class)
        ->call('addToCart', $vatable->id)
        ->call('addToCart', $zeroRated->id)
        ->assertHasNoErrors();

    expect(session('pos.employee.'.$employee->id.'.cart'))->toHaveKey($vatable->id)
        ->and(session('pos.employee.'.$employee->id.'.cart'))->toHaveKey($zeroRated->id);

    $component
        ->call('setSaleTaxClassification', TaxClassification::ZERO_RATED)
        ->assertHasNoErrors()
        ->assertSet('saleTaxClassification', TaxClassification::ZERO_RATED);

    expect($component->viewData('vat'))->toBe(0)
        ->and($component->viewData('total'))->toBe(200);
});

test('checkout applies the selected transaction classification to a preloaded cart', function () {
    $employee = createTaxClassificationEmployee();
    $this->actingAs($employee);
    $vatable = createTaxClassificationItem(['name' => 'Taxable board']);
    $zeroRated = createTaxClassificationItem([
        'name' => 'Zero-rated board', 'tax_classification' => TaxClassification::ZERO_RATED,
    ]);
    setupPosAccountingBooks([$vatable, $zeroRated]);

    session()->put('pos.employee.'.$employee->id.'.cart', [
        $vatable->id => ['quantity' => 1],
        $zeroRated->id => ['quantity' => 1],
    ]);

    Livewire::test(PointOfSale::class)
        ->assertDontSee('Incompatible items in this sale.')
        ->call('setSaleTaxClassification', TaxClassification::ZERO_RATED)
        ->assertHasNoErrors()
        ->set('amountReceived', '200.00')
        ->call('checkout')
        ->assertHasNoErrors();

    $transaction = PosTransaction::query()->with(['lines', 'vatRecord'])->sole();

    expect($transaction->tax_classification)->toBe(TaxClassification::ZERO_RATED)
        ->and($transaction->vat_amount)->toBe('0.00')
        ->and($transaction->total)->toBe('200.00')
        ->and($transaction->lines)->toHaveCount(2)
        ->and($transaction->lines->pluck('tax_classification')->unique()->all())->toBe([TaxClassification::ZERO_RATED])
        ->and($transaction->vatRecord->tax_classification)->toBe(TaxClassification::ZERO_RATED)
        ->and($transaction->vatRecord->zero_rated_sales)->toBe('200.00');
});

test('POS keeps the transaction classification through quantity changes and saves it everywhere', function () {
    $this->actingAs(createTaxClassificationEmployee());
    $item = createTaxClassificationItem([
        'name' => 'Zero-rated board',
        'selling_price' => 100,
        'tax_classification' => TaxClassification::ZERO_RATED,
    ]);
    setupPosAccountingBooks([$item]);

    Livewire::test(PointOfSale::class)
        ->call('addToCart', $item->id)
        ->call('addToCart', $item->id)
        ->call('setQuantity', $item->id, 2)
        ->assertSet('saleTaxClassification', TaxClassification::ZERO_RATED)
        ->set('amountReceived', '200.00')
        ->call('checkout')
        ->assertHasNoErrors();

    $transaction = PosTransaction::query()->with(['lines', 'vatRecord'])->sole();

    expect($transaction->tax_classification)->toBe(TaxClassification::ZERO_RATED)
        ->and($transaction->vat_rate)->toBe('0.0000')
        ->and($transaction->vat_amount)->toBe('0.00')
        ->and($transaction->total)->toBe('200.00')
        ->and($transaction->lines[0]->tax_classification)->toBe(TaxClassification::ZERO_RATED)
        ->and($transaction->vatRecord->tax_classification)->toBe(TaxClassification::ZERO_RATED)
        ->and($transaction->vatRecord->zero_rated_sales)->toBe('200.00')
        ->and($transaction->vatRecord->taxable_sales)->toBe('0.00');
});

test('POS saves the selected transaction classification for every supported classification', function (string $selected, string $salesColumn, float $expectedVat, float $expectedTotal) {
    $this->actingAs(createTaxClassificationEmployee());
    $item = createTaxClassificationItem([
        'selling_price' => 100,
        'unit_cost' => 50,
        'tax_classification' => $selected,
    ]);
    setupPosAccountingBooks([$item]);

    Livewire::test(PointOfSale::class)
        ->call('setSaleTaxClassification', $selected)
        ->call('addToCart', $item->id)
        ->set('amountReceived', number_format($expectedTotal, 2, '.', ''))
        ->call('checkout')
        ->assertHasNoErrors();

    $transaction = PosTransaction::query()->with(['lines', 'vatRecord'])->sole();
    $record = $transaction->vatRecord;

    expect($transaction->tax_classification)->toBe($selected)
        ->and($transaction->vat_rate)->toBe(number_format($expectedVat > 0 ? 0.12 : 0, 4, '.', ''))
        ->and($transaction->vat_amount)->toBe(number_format($expectedVat, 2, '.', ''))
        ->and($transaction->total)->toBe(number_format($expectedTotal, 2, '.', ''))
        ->and($transaction->lines[0]->tax_classification)->toBe($selected)
        ->and($record->tax_classification)->toBe($selected)
        ->and($record->{$salesColumn})->toBe('100.00')
        ->and($record->taxable_sales)->toBe($selected === TaxClassification::VATABLE ? '100.00' : '0.00')
        ->and($record->output_vat)->toBe(number_format($expectedVat, 2, '.', ''))
        ->and($record->total)->toBe(number_format($expectedTotal, 2, '.', ''));

    Livewire::test(PointOfSale::class)
        ->call('viewReceipt', $transaction->id)
        ->assertSee('Receipt · Completed transaction')
        ->assertSee(TaxClassification::label($selected));
})->with([
    'VATable Sales' => [TaxClassification::VATABLE, 'taxable_sales', 12.0, 112.0],
    'Zero-Rated Sales' => [TaxClassification::ZERO_RATED, 'zero_rated_sales', 0.0, 100.0],
    'VAT-Exempt Sales' => [TaxClassification::VAT_EXEMPT, 'exempt_sales', 0.0, 100.0],
    'Non-VAT / Outside Scope' => [TaxClassification::NON_VAT, 'non_vat_sales', 0.0, 100.0],
]);

test('POS tax classification filter limits the material list to the saved classification', function () {
    $this->actingAs(createTaxClassificationEmployee());
    createTaxClassificationItem(['name' => 'Taxable board']);
    createTaxClassificationItem([
        'name' => 'Zero-rated board',
        'tax_classification' => TaxClassification::ZERO_RATED,
    ]);
    createTaxClassificationItem([
        'name' => 'Exempt board',
        'tax_classification' => TaxClassification::VAT_EXEMPT,
    ]);
    createTaxClassificationItem([
        'name' => 'Outside-scope board',
        'tax_classification' => TaxClassification::NON_VAT,
    ]);

    Livewire::test(PointOfSale::class)
        ->assertSee('Taxable board')
        ->assertSee('Zero-rated board')
        ->set('taxClassification', TaxClassification::ZERO_RATED)
        ->assertSee('Zero-rated board')
        ->assertDontSee('Taxable board')
        ->assertDontSee('Exempt board')
        ->assertDontSee('Outside-scope board')
        ->set('taxClassification', '')
        ->assertSee('Taxable board');
});

test('POS shows the tax classification only in the current sale, not on material cards or cart lines', function () {
    $this->actingAs(createTaxClassificationEmployee());
    $item = createTaxClassificationItem(['name' => 'Taxable board']);

    $component = Livewire::test(PointOfSale::class)
        ->call('addToCart', $item->id)
        ->assertDontSeeHtml('pos-product-tax')
        ->assertDontSeeHtml('pos-cart-line-tax')
        ->assertDontSee('Filter by classification')
        ->assertDontSeeHtml('pos-tax-classification')
        ->assertSeeHtml('pos-cart-classification')
        ->assertSee('One classification applies to every item in this sale.');

    $html = $component->html();
    $materialCards = Str::between($html, '<div class="pos-product-grid">', '<aside class="pos-cart-card');
    $cartLines = Str::between($html, '<div class="pos-cart-lines">', '<div class="pos-cart-classification">');

    expect($materialCards)->toContain('Taxable board')
        ->and($materialCards)->not->toContain('VATable Sales')
        ->and($cartLines)->toContain('Taxable board')
        ->and($cartLines)->not->toContain('VATable Sales');
});

test('inventory add and edit forms no longer expose a tax classification field', function () {
    $this->actingAs(createTaxClassificationEmployee([
        'pos.view', 'pos.checkout', 'tax.view', 'inventory.view', 'inventory.create', 'inventory.update',
    ]));
    $existing = createTaxClassificationItem([
        'name' => 'Existing zero-rated cement',
        'tax_classification' => TaxClassification::ZERO_RATED,
    ]);

    $this->get(route('inventory.management.create'))
        ->assertOk()
        ->assertSee('Add inventory item')
        ->assertDontSee('Tax classification');

    $this->get(route('inventory.management.edit', $existing->id))
        ->assertOk()
        ->assertSee('Edit inventory item')
        ->assertDontSee('Tax classification');

    Livewire::test(InventoryManagement::class)
        ->set('name', 'New cement')
        ->set('category', 'Concrete')
        ->set('qty', '0')
        ->set('unit', 'bag')
        ->set('unitCost', '100')
        ->set('sellingPrice', '120')
        ->set('reorderLevel', '1')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(InventoryManagement::class)
        ->call('edit', $existing->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Inventory::query()->where('name', 'New cement')->sole()->tax_classification)
        ->toBe(TaxClassification::VATABLE)
        ->and($existing->fresh()->tax_classification)->toBe(TaxClassification::ZERO_RATED);
});

test('completed sales with each classification are reflected in VAT records, summary, report, and return preparation', function () {
    $this->actingAs(createTaxClassificationEmployee());
    $vatable = createTaxClassificationItem();
    $zeroRated = createTaxClassificationItem([
        'name' => 'Zero-rated board', 'selling_price' => 50, 'unit_cost' => 20,
        'tax_classification' => TaxClassification::ZERO_RATED,
    ]);
    $exempt = createTaxClassificationItem([
        'name' => 'Exempt board', 'selling_price' => 30, 'unit_cost' => 10,
        'tax_classification' => TaxClassification::VAT_EXEMPT,
    ]);
    $nonVat = createTaxClassificationItem([
        'name' => 'Outside-scope board', 'selling_price' => 20, 'unit_cost' => 5,
        'tax_classification' => TaxClassification::NON_VAT,
    ]);
    setupPosAccountingBooks([$vatable, $zeroRated, $exempt, $nonVat]);

    foreach ([
        TaxClassification::VATABLE => [$vatable, '112.00'],
        TaxClassification::ZERO_RATED => [$zeroRated, '50.00'],
        TaxClassification::VAT_EXEMPT => [$exempt, '30.00'],
        TaxClassification::NON_VAT => [$nonVat, '20.00'],
    ] as $classification => [$item, $amountReceived]) {
        Livewire::test(PointOfSale::class)
            ->call('setSaleTaxClassification', $classification)
            ->call('addToCart', $item->id)
            ->set('amountReceived', $amountReceived)
            ->call('checkout')
            ->assertHasNoErrors();
    }

    $today = now('Asia/Manila');
    $summary = app(RecordedVatSummary::class)->forMonth($today->year, $today->month);

    expect($summary)->toMatchArray([
        'record_count' => 4,
        'taxable_sales' => '100.00',
        'zero_rated_sales' => '50.00',
        'exempt_sales' => '30.00',
        'non_vat_sales' => '20.00',
        'output_vat' => '12.00',
        'total_sales' => '212.00',
    ]);

    Livewire::test(VatRecords::class)
        ->assertSee('VATable Sales')
        ->assertSee('Zero-Rated Sales')
        ->assertSee('VAT-Exempt Sales')
        ->assertSee('Non-VAT / Outside Scope')
        ->assertSee('₱12.00');

    Livewire::test(VatSummary::class)
        ->assertSee('Zero-rated sales')
        ->assertSee('VAT-exempt sales')
        ->assertSee('Non-VAT / outside scope')
        ->assertSee('₱50.00')
        ->assertSee('₱30.00')
        ->assertSee('₱20.00');

    Livewire::test(TaxReport::class)
        ->assertSee('Zero-rated sales')
        ->assertSee('VAT-exempt sales')
        ->assertSee('Non-VAT / outside scope sales')
        ->assertSee('VATable Sales')
        ->assertSee('Zero-Rated Sales')
        ->assertSee('₱50.00')
        ->assertSee('₱30.00')
        ->assertSee('₱20.00');

    Livewire::test(VatReturnPreparation::class)
        ->assertSee('Zero-rated sales')
        ->assertSee('VAT-exempt sales')
        ->assertSee('Non-VAT / outside scope sales')
        ->assertSee('VATable Sales')
        ->assertSee('Zero-Rated Sales')
        ->assertSee('₱50.00')
        ->assertSee('₱30.00')
        ->assertSee('₱20.00');
});
