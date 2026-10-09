<?php

use App\Livewire\Inventory\InventoryManagement;
use App\Livewire\Inventory\InventoryOverview;
use App\Models\Employee;
use App\Models\Inventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createInventoryManager(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'inventory.manager',
        'email' => 'inventory@example.com',
        'password' => Hash::make('secret-password'),
    ]), ['inventory.view', 'inventory.create', 'inventory.update', 'inventory.delete', 'inventory.movements.record']);
}

function inventoryAttributes(array $overrides = []): array
{
    return array_merge([
        'name' => 'Reclaimed timber',
        'category' => 'Lumber',
        'qty' => 12,
        'unit' => 'piece',
        'unit_cost' => 7.25,
        'reorder_level' => 3,
    ], $overrides);
}

test('inventory management rejects a negative selling price without persisting an item', function () {
    $this->actingAs(createInventoryManager());

    Livewire::test(InventoryManagement::class)
        ->set('name', 'Invalid price item')
        ->set('category', 'Lumber')
        ->set('qty', '12')
        ->set('unit', 'piece')
        ->set('unitCost', '7.25')
        ->set('sellingPrice', '-1')
        ->set('reorderLevel', '3')
        ->call('save')
        ->assertHasErrors(['sellingPrice' => 'min']);

    expect(Inventory::count())->toBe(0);
});

test('authenticated employees can create, search, edit, and delete priced inventory', function () {
    $this->actingAs(createInventoryManager());

    Livewire::test(InventoryManagement::class)
        ->set('name', 'Reclaimed timber')
        ->set('category', 'Lumber')
        ->set('qty', '12')
        ->set('unit', 'piece')
        ->set('unitCost', '7.25')
        ->set('sellingPrice', '11.50')
        ->set('reorderLevel', '3')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Reclaimed timber')
        ->assertSee('11.50');

    $item = Inventory::firstOrFail();
    expect($item->unit_cost)->toBe('7.25')
        ->and($item->selling_price)->toBe('11.50');

    $otherItem = Inventory::create(inventoryAttributes([
        'name' => 'Concrete block',
        'category' => 'Concrete',
        'selling_price' => 9.5,
    ]));

    Livewire::test(InventoryManagement::class)
        ->set('search', 'reclaimed')
        ->assertSee('Reclaimed timber')
        ->assertDontSeeHtml('<td><strong>'.$otherItem->code.'</strong><br>Concrete block</td>')
        ->set('search', '')
        ->set('categoryFilter', 'Concrete')
        ->assertSeeHtml('<td><strong>'.$otherItem->code.'</strong><br>Concrete block</td>')
        ->assertDontSeeHtml('<td><strong>'.$item->code.'</strong><br>Reclaimed timber</td>')
        ->call('edit', $item->id)
        ->set('sellingPrice', '12.00')
        ->call('save')
        ->assertHasNoErrors()
        ->set('stockItemId', $item->id)
        ->set('stockQuantity', '2.50')
        ->set('stockReasonCategory', 'purchase_receipt')
        ->set('stockReference', 'GRN-100')
        ->set('stockEffectiveDate', '2026-10-08')
        ->call('recordStockIn')
        ->assertHasNoErrors();

    expect($item->fresh()->qty)->toBe('14.50')
        ->and($item->fresh()->selling_price)->toBe('12.00');

    Livewire::test(InventoryManagement::class)
        ->call('deactivate', $item->id)
        ->assertSee('Reclaimed timber')
        ->assertSee('Inactive');
    expect($item->fresh()->status)->toBe('inactive')
        ->and(Inventory::find($item->id))->not->toBeNull();
});

test('legacy inventory keeps its cost and has no selling price until manually priced', function () {
    $this->actingAs(createInventoryManager());

    $legacyItem = Inventory::create(inventoryAttributes([
        'name' => 'Legacy pine board',
        'unit_cost' => 42.75,
    ]));

    Livewire::test(InventoryManagement::class)
        ->assertSee('Legacy pine board')
        ->assertSee('Not priced')
        ->assertSee('42.75');

    expect($legacyItem->fresh()->unit_cost)->toBe('42.75')
        ->and($legacyItem->fresh()->selling_price)->toBeNull()
        ->and(Inventory::availableForSale()->pluck('id')->all())->not->toContain($legacyItem->id);
});

test('only priced positive-stock inventory is available for sale', function () {
    $legacyItem = Inventory::create(inventoryAttributes([
        'name' => 'Legacy pine board',
        'unit_cost' => 42.75,
    ]));
    $pricedItem = Inventory::create(inventoryAttributes([
        'name' => 'Priced timber',
        'selling_price' => 18.50,
    ]));
    $outOfStockItem = Inventory::create(inventoryAttributes([
        'name' => 'Empty stock',
        'qty' => 0,
        'selling_price' => 5,
    ]));

    $available = Inventory::availableForSale()->pluck('id')->all();

    expect($available)->toContain($pricedItem->id)
        ->not->toContain($legacyItem->id)
        ->not->toContain($outOfStockItem->id);
});

test('inventory management renders inside the authenticated application shell', function () {
    $this->actingAs(createInventoryManager())
        ->get(route('inventory.management'))
        ->assertOk()
        ->assertSee('Manage inventory')
        ->assertSee('Application navigation');
});

test('inventory JSON endpoints keep their existing authenticated response contracts', function () {
    $this->actingAs(createInventoryManager());

    $csrfToken = 'inventory-json-test-token';
    $this->withSession(['_token' => $csrfToken])
        ->withHeader('X-CSRF-TOKEN', $csrfToken);
    $this->getJson(route('inventory.index'))
        ->assertOk()
        ->assertExactJson([]);

    $this->postJson(route('inventory.store'), inventoryAttributes([
        'selling_price' => 16.50,
    ]))
        ->assertCreated()
        ->assertJsonPath('name', 'Reclaimed timber')
        ->assertJsonPath('selling_price', '16.50');

    $item = Inventory::firstOrFail();

    $updateAttributes = inventoryAttributes([
        'name' => 'Updated timber',
        'selling_price' => 18.25,
    ]);
    unset($updateAttributes['qty']);

    $this->putJson(route('inventory.update', $item), $updateAttributes)
        ->assertOk()
        ->assertJsonPath('message', 'Inventory item updated successfully.')
        ->assertJsonPath('inventory.name', 'Updated timber')
        ->assertJsonPath('inventory.selling_price', '18.25')
        ->assertJsonPath('inventory.qty', '12.00');

    $this->deleteJson(route('inventory.destroy', $item))
        ->assertOk()
        ->assertJsonPath('message', 'Inventory item deactivated successfully.');

    expect($item->fresh()->status)->toBe('inactive');
});
test('inventory management screen requires authentication', function () {
    $this->get(route('inventory.management'))->assertRedirect(route('login'));
});
test('inventory overview requires authentication', function () {
    $this->get(route('inventory.overview'))->assertRedirect(route('login'));
});

test('inventory overview shows persisted inventory calculations and filters', function () {
    $this->actingAs(createInventoryManager());

    $reorderItem = Inventory::create(inventoryAttributes([
        'name' => 'Reclaimed timber',
        'qty' => 3.5,
        'unit_cost' => 7.25,
        'selling_price' => 11.50,
        'reorder_level' => 4,
    ]));
    Inventory::query()->whereKey($reorderItem->id)->update(['updated_at' => '2026-01-02 12:00:00']);
    Inventory::create(inventoryAttributes([
        'name' => 'Concrete block',
        'category' => 'Concrete',
        'qty' => 8,
        'unit_cost' => 4,
        'selling_price' => null,
    ]));

    $this->get(route('inventory.overview'))
        ->assertOk()
        ->assertSee('Manage inventory')
        ->assertSee('Application navigation')
        ->assertSee(route('inventory.management'), false);

    Livewire::test(InventoryOverview::class)
        ->assertSee('2 materials')
        ->assertSee('Reclaimed timber')
        ->assertSee('3.50 piece')
        ->assertSee('7.25')
        ->assertSee('11.50')
        ->assertSee('25.38')
        ->assertSee('Reorder')
        ->assertSee('Jan 2, 2026')
        ->set('search', 'concrete')
        ->assertSee('1 material')
        ->assertSee('Concrete block')
        ->assertDontSee('Reclaimed timber')
        ->set('search', '')
        ->set('categoryFilter', 'Lumber')
        ->assertSee('Reclaimed timber')
        ->assertDontSee('Concrete block');
});

test('inventory overview reflects changes made in inventory management', function () {
    $this->actingAs(createInventoryManager());

    $item = Inventory::create(inventoryAttributes([
        'name' => 'Reclaimed timber',
        'qty' => 12,
        'selling_price' => 11.50,
    ]));

    Livewire::test(InventoryManagement::class)
        ->call('edit', $item->id)
        ->set('sellingPrice', '12.25')
        ->call('save')
        ->assertHasNoErrors()
        ->set('stockItemId', $item->id)
        ->set('stockQuantity', '3')
        ->set('stockReasonCategory', 'purchase_receipt')
        ->set('stockEffectiveDate', '2026-10-08')
        ->call('recordStockIn')
        ->assertHasNoErrors();

    Livewire::test(InventoryOverview::class)
        ->assertSee('Reclaimed timber')
        ->assertSee('15.00 piece')
        ->assertSee('12.25');

    Livewire::test(InventoryManagement::class)
        ->call('deactivate', $item->id)
        ->assertSee('Reclaimed timber')
        ->assertSee('Inactive');

    Livewire::test(InventoryOverview::class)
        ->assertSee('Reclaimed timber')
        ->assertSee('15.00 piece');
});

test('inventory management persists uploaded images and preserves them when edited without a replacement', function () {
    Storage::fake('public');
    $this->actingAs(createInventoryManager());

    Livewire::test(InventoryManagement::class)
        ->set('name', 'Image timber')
        ->set('category', 'Lumber')
        ->set('qty', '12')
        ->set('unit', 'piece')
        ->set('unitCost', '7.25')
        ->set('sellingPrice', '11.50')
        ->set('reorderLevel', '3')
        ->set('image', UploadedFile::fake()->image('timber.png'))
        ->call('save')
        ->assertHasNoErrors();

    $item = Inventory::firstOrFail();
    expect($item->image)->toStartWith('inventory/')
        ->and($item->fresh()->selling_price)->toBe('11.50');
    Storage::disk('public')->assertExists($item->image);

    Livewire::test(InventoryManagement::class)
        ->call('edit', $item->id)
        ->set('sellingPrice', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($item->fresh()->image)->toBe($item->image)
        ->and($item->fresh()->selling_price)->toBeNull()
        ->and($item->fresh()->unit_cost)->toBe('7.25');
    Storage::disk('public')->assertExists($item->image);
});
test('new inventory receives a sequential code and opening stock ledger entry; later stock-in is audited and immutable', function () {
    $this->actingAs(createInventoryManager());

    Livewire::test(InventoryManagement::class)
        ->set('name', 'Ledger timber')
        ->set('category', 'Lumber')
        ->set('qty', '7.25')
        ->set('unit', 'piece')
        ->set('unitCost', '4.50')
        ->set('sellingPrice', '8.00')
        ->set('description', 'Reclaimed stock')
        ->call('save')
        ->assertHasNoErrors();

    $item = Inventory::firstOrFail();
    $nextItem = Inventory::create(inventoryAttributes(['name' => 'Next ledger item', 'qty' => 0]));
    expect($item->code)->toBe('INV-'.str_pad((string) $item->id, 6, '0', STR_PAD_LEFT))
        ->and($nextItem->code)->toBe('INV-'.str_pad((string) $nextItem->id, 6, '0', STR_PAD_LEFT))
        ->and($item->code)->not->toBe($nextItem->code)
        ->and($item->description)->toBe('Reclaimed stock')
        ->and($item->stockMovements()->count())->toBe(1)
        ->and($item->stockMovements()->first()->type)->toBe('opening_balance');

    Livewire::test(InventoryManagement::class)
        ->set('stockItemId', $item->id)
        ->set('stockQuantity', '2')
        ->set('stockReasonCategory', 'return')
        ->set('stockNotes', 'Customer return')
        ->set('stockReference', 'RET-22')
        ->set('stockEffectiveDate', '2026-10-01')
        ->call('recordStockIn')
        ->assertHasNoErrors()
        ->call('showHistory', $item->id)
        ->assertSee('effective 2026-10-01')
        ->assertSee('posted ')
        ->assertSee('RET-22');

    $movement = $item->stockMovements()->where('type', 'stock_in')->firstOrFail();
    expect($item->fresh()->qty)->toBe('9.25')
        ->and($movement->posted_by)->toBe(auth()->id())
        ->and($movement->reason_category)->toBe('return')
        ->and($movement->quantity)->toBe('2.00')
        ->and(fn () => $movement->update(['quantity' => '5.00']))->toThrow(LogicException::class)
        ->and(fn () => $movement->delete())->toThrow(LogicException::class);

    Livewire::test(InventoryManagement::class)
        ->call('reverseStockMovement', $movement->id)
        ->assertHasNoErrors()
        ->call('showHistory', $item->id)
        ->assertSee('Reversed by movement #');

    $reversal = $movement->fresh()->reversal;
    expect($item->fresh()->qty)->toBe('7.25')
        ->and($reversal->type)->toBe('reversal')
        ->and($reversal->quantity)->toBe('-2.00')
        ->and($reversal->reverses_movement_id)->toBe($movement->id)
        ->and($item->fresh()->unit_cost)->toBe('4.50');

    Livewire::test(InventoryManagement::class)
        ->set('stockItemId', $item->id)
        ->set('stockQuantity', '2.50')
        ->set('stockReasonCategory', 'purchase_receipt')
        ->set('stockReference', 'RET-22-CORRECTED')
        ->set('stockEffectiveDate', '2026-10-01')
        ->call('recordStockIn')
        ->assertHasNoErrors();

    expect($item->fresh()->qty)->toBe('9.75')
        ->and($movement->fresh()->quantity)->toBe('2.00');
});


test('stock-in is separately permission-gated and inactive items are unavailable to POS', function () {
    $employee = grantEmployeeTestPermissions(Employee::create([
        'username' => 'inventory.viewer',
        'email' => 'inventory.viewer@example.com',
        'password' => Hash::make('secret-password'),
    ]), ['inventory.view']);
    $active = Inventory::create(inventoryAttributes(['selling_price' => 12]));
    $inactive = Inventory::create(inventoryAttributes([
        'name' => 'Inactive timber',
        'status' => 'inactive',
        'selling_price' => 12,
    ]));

    $this->actingAs($employee);
    Livewire::test(InventoryManagement::class)
        ->set('stockItemId', $active->id)
        ->set('stockQuantity', '1')
        ->set('stockReasonCategory', 'purchase_receipt')
        ->set('stockEffectiveDate', '2026-10-08')
        ->call('recordStockIn')
        ->assertForbidden();

    expect(Inventory::availableForSale()->pluck('id')->all())
        ->toContain($active->id)
        ->not->toContain($inactive->id);

    expect(fn () => app(\App\Services\Inventory\RecordStockIn::class)->handle(
        $inactive->id,
        1,
        'other',
        null,
        null,
        '2026-10-08',
        (int) $employee->id,
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('inventory page navigation offers both overview and management', function () {
    $this->actingAs(createInventoryManager());

    $this->get(route('inventory.overview'))
        ->assertOk()
        ->assertSee(route('inventory.overview'), false)
        ->assertSee(route('inventory.management'), false);

    $this->get(route('inventory.management'))
        ->assertOk()
        ->assertSee(route('inventory.overview'), false)
        ->assertSee(route('inventory.management'), false);
});
test('authorized staff can record stock-out and counted-balance adjustments in the immutable ledger', function () {
    $employee = createInventoryManager();
    $this->actingAs($employee);
    $item = Inventory::create(inventoryAttributes(['qty' => 10]));

    Livewire::test(InventoryManagement::class)
        ->set('issueItemId', $item->id)
        ->set('issueQuantity', '3')
        ->set('issueReasonCategory', 'project_use')
        ->set('issueNotes', 'Used on site')
        ->set('issueReference', 'JOB-42')
        ->call('recordStockOut')
        ->assertHasNoErrors()
        ->assertSee('Post stock-out')
        ->assertSee('7.00 piece');

    expect($item->fresh()->qty)->toBe('7.00');

    Livewire::test(InventoryManagement::class)
        ->set('adjustmentItemId', $item->id)
        ->set('countedQuantity', '5.50')
        ->set('adjustmentReasonCategory', 'count_correction')
        ->set('adjustmentEffectiveDate', now()->toDateString())
        ->set('adjustmentReference', 'COUNT-9')
        ->call('recordStockAdjustment')
        ->assertHasNoErrors()
        ->assertSee('5.50 piece')
        ->call('showHistory', $item->id)
        ->assertSee('Stock adjustment')
        ->assertSee('-1.50')
        ->assertSee('COUNT-9')
        ->assertSee('posted ');

    $stockOut = $item->stockMovements()->where('type', 'stock_out')->firstOrFail();
    $adjustment = $item->stockMovements()->where('type', 'adjustment')->firstOrFail();
    expect($item->fresh()->qty)->toBe('5.50')
        ->and($stockOut->quantity)->toBe('-3.00')
        ->and($stockOut->reason_category)->toBe('project_use')
        ->and($adjustment->quantity)->toBe('-1.50')
        ->and($adjustment->reason_category)->toBe('count_correction')
        ->and($adjustment->posted_by)->toBe($employee->id)
        ->and(fn () => $adjustment->update(['quantity' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $adjustment->delete())->toThrow(LogicException::class);

    Livewire::test(InventoryManagement::class)
        ->call('reverseStockMovement', $adjustment->id)
        ->assertHasNoErrors();

    expect($item->fresh()->qty)->toBe('7.00')
        ->and($adjustment->fresh()->reversal->reverses_movement_id)->toBe($adjustment->id);

    Livewire::test(InventoryManagement::class)
        ->set('adjustmentItemId', $item->id)
        ->set('countedQuantity', '6.50')
        ->set('adjustmentReasonCategory', 'count_correction')
        ->set('adjustmentEffectiveDate', now()->toDateString())
        ->set('adjustmentReference', 'COUNT-9-CORRECTED')
        ->call('recordStockAdjustment')
        ->assertHasNoErrors();

    expect($item->fresh()->qty)->toBe('6.50')
        ->and($adjustment->fresh()->quantity)->toBe('-1.50');
});
test('backdated stock-out and adjustment are rejected when a later balance would become negative', function () {
    $employee = grantEmployeeTestPermissions(Employee::create([
        'username' => 'inventory.viewer',
        'email' => 'inventory.viewer@example.com',
        'password' => Hash::make('secret-password'),
    ]), ['inventory.view']);
    $item = Inventory::create(inventoryAttributes(['qty' => 0]));
    $yesterday = now()->subDay()->toDateString();
    $today = now()->toDateString();

    $this->actingAs($employee);
    Livewire::test(InventoryManagement::class)
        ->set('issueItemId', $item->id)
        ->set('issueQuantity', '1')
        ->set('issueReasonCategory', 'sale')
        ->set('issueEffectiveDate', $today)
        ->call('recordStockOut')
        ->assertForbidden();

    $this->actingAs(createInventoryManager());
    app(\App\Services\Inventory\RecordStockIn::class)->handle(
        $item->id,
        5,
        'purchase_receipt',
        null,
        null,
        $yesterday,
        (int) auth()->id(),
    );

    Livewire::test(InventoryManagement::class)
        ->set('issueItemId', $item->id)
        ->set('issueQuantity', '4')
        ->set('issueReasonCategory', 'project_use')
        ->set('issueEffectiveDate', $today)
        ->call('recordStockOut')
        ->assertHasNoErrors();

    Livewire::test(InventoryManagement::class)
        ->set('issueItemId', $item->id)
        ->set('issueQuantity', '2')
        ->set('issueReasonCategory', 'sale')
        ->set('issueEffectiveDate', $yesterday)
        ->call('recordStockOut')
        ->assertHasErrors('issueQuantity');

    Livewire::test(InventoryManagement::class)
        ->set('adjustmentItemId', $item->id)
        ->set('countedQuantity', '2')
        ->set('adjustmentReasonCategory', 'count_correction')
        ->set('adjustmentEffectiveDate', $yesterday)
        ->call('recordStockAdjustment')
        ->assertHasErrors('countedQuantity');

    expect($item->fresh()->qty)->toBe('1.00')
        ->and($item->stockMovements()->where('type', 'stock_out')->count())->toBe(1)
        ->and($item->stockMovements()->where('type', 'adjustment')->exists())->toBeFalse();
});
