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
    return Employee::create([
        'username' => 'inventory.manager',
        'email' => 'inventory@example.com',
        'password' => Hash::make('secret-password'),
    ]);
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

    Inventory::create(inventoryAttributes([
        'name' => 'Concrete block',
        'category' => 'Concrete',
        'selling_price' => 9.5,
    ]));

    Livewire::test(InventoryManagement::class)
        ->set('search', 'reclaimed')
        ->assertSee('Reclaimed timber')
        ->assertDontSee('Concrete block')
        ->set('search', '')
        ->set('categoryFilter', 'Concrete')
        ->assertSee('Concrete block')
        ->assertDontSee('Reclaimed timber')
        ->call('edit', $item->id)
        ->set('sellingPrice', '12.00')
        ->call('save')
        ->assertSee('12.00');

    expect($item->fresh()->selling_price)->toBe('12.00');
    Livewire::test(InventoryManagement::class)
        ->call('delete', $item->id)
        ->assertDontSee('Reclaimed timber');

    expect(Inventory::find($item->id))->toBeNull();
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

    $this->putJson(route('inventory.update', $item), inventoryAttributes([
        'name' => 'Updated timber',
        'selling_price' => 18.25,
    ]))
        ->assertOk()
        ->assertJsonPath('message', 'Inventory item updated successfully.')
        ->assertJsonPath('inventory.name', 'Updated timber')
        ->assertJsonPath('inventory.selling_price', '18.25');

    $this->deleteJson(route('inventory.destroy', $item))
        ->assertOk()
        ->assertJsonPath('message', 'Inventory item deleted successfully.');

    expect(Inventory::find($item->id))->toBeNull();
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
        ->set('qty', '9')
        ->set('sellingPrice', '12.25')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(InventoryOverview::class)
        ->assertSee('Reclaimed timber')
        ->assertSee('9.00 piece')
        ->assertSee('12.25');

    Livewire::test(InventoryManagement::class)
        ->call('delete', $item->id)
        ->assertDontSee('Reclaimed timber');

    Livewire::test(InventoryOverview::class)
        ->assertDontSee('Reclaimed timber')
        ->assertSee('No inventory items match these filters.');
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
