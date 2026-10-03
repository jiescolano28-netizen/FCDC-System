<?php

use App\Livewire\InventoryManagement;
use App\Models\Employee;
use App\Models\Inventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
        ->assertSee('12.00')
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

test('inventory management screen requires authentication', function () {
    $this->get(route('inventory.management'))->assertRedirect(route('login'));
});
