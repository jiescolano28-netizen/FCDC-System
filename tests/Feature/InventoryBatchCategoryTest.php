<?php

use App\Livewire\Inventory\BatchCategoryManagement;
use App\Livewire\Inventory\InventoryManagement;
use App\Livewire\Inventory\InventoryOverview;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\InventoryBatchCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function batchCategoryEmployee(array $permissions = ['inventory.view', 'inventory.create', 'inventory.update', 'inventory.delete']): Employee
{
    static $counter = 0;
    $counter++;

    $employee = Employee::create([
        'username' => 'batch.category.manager.'.$counter,
        'email' => 'batch.category'.$counter.'@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    return grantEmployeeTestPermissions($employee, $permissions);
}

test('inventory management provides button links to add an item and manage batch categories', function () {
    $this->actingAs(batchCategoryEmployee());

    Livewire::test(InventoryManagement::class)
        ->assertSee('Batch Categories')
        ->assertSeeHtml('class="primary-link" href="'.route('inventory.batch-categories').'">Batch Categories</a>')
        ->assertSeeHtml('class="primary-link" href="'.route('inventory.management.create').'">Add Inventory Item</a>');
});

test('batch categories support create, update, soft delete, listing and restore with audit users', function () {
    $employee = batchCategoryEmployee();
    $this->actingAs($employee);

    $this->get(route('inventory.batch-categories'))
        ->assertOk()
        ->assertSee('Inventory batch categories')
        ->assertSee('Batch categories')
        ->assertSee('id="batch-category-modal"', false);

    Livewire::test(BatchCategoryManagement::class)
        ->call('create')
        ->assertDispatched('batch-category-modal-open')
        ->set('name', 'Raw materials')
        ->set('description', 'Unprocessed stock')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('batch-category-saved')
        ->assertSee('Raw materials');

    $category = InventoryBatchCategory::query()->sole();
    expect($category->created_by)->toBe($employee->id)
        ->and($category->updated_by)->toBe($employee->id)
        ->and($category->description)->toBe('Unprocessed stock');

    $editor = batchCategoryEmployee(['inventory.update']);
    $this->actingAs($editor);
    Livewire::test(BatchCategoryManagement::class)
        ->call('edit', $category->id)
        ->assertDispatched('batch-category-modal-open')
        ->assertSet('name', 'Raw materials')
        ->set('name', 'Bulk materials')
        ->set('description', 'Grouped bulk stock')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Bulk materials');

    expect($category->fresh()->name)->toBe('Bulk materials')
        ->and($category->fresh()->created_by)->toBe($employee->id)
        ->and($category->fresh()->updated_by)->toBe($editor->id);

    $this->actingAs($employee);

    Livewire::test(BatchCategoryManagement::class)
        ->call('delete', $category->id)
        ->assertDontSee('Bulk materials');
    expect($category->fresh()->trashed())->toBeTrue();

    Livewire::test(BatchCategoryManagement::class)
        ->set('showDeleted', true)
        ->assertSee('Bulk materials')
        ->call('restore', $category->id);

    expect($category->fresh()->trashed())->toBeFalse();
});

test('batch category page and actions require inventory permissions', function () {
    $viewer = batchCategoryEmployee(['inventory.view']);
    $this->actingAs($viewer);

    $this->get(route('inventory.batch-categories'))->assertOk();
    Livewire::test(BatchCategoryManagement::class)
        ->set('name', 'Unauthorized category')
        ->call('save')
        ->assertForbidden();
    expect(InventoryBatchCategory::count())->toBe(0);

    $withoutView = batchCategoryEmployee([]);
    $this->actingAs($withoutView)->get(route('inventory.batch-categories'))->assertForbidden();
});

test('inventory items can optionally be assigned to a batch category and the assignment can be changed or cleared', function () {
    $employee = batchCategoryEmployee();
    $this->actingAs($employee);

    $category = InventoryBatchCategory::create([
        'name' => 'Raw materials',
        'description' => null,
        'created_by' => $employee->id,
        'updated_by' => $employee->id,
    ]);
    $otherCategory = InventoryBatchCategory::create([
        'name' => 'Finished goods',
        'description' => null,
        'created_by' => $employee->id,
        'updated_by' => $employee->id,
    ]);

    $this->get(route('inventory.management.create'))
        ->assertOk()
        ->assertSee('Batch category (optional)')
        ->assertSee('No batch category')
        ->assertSee('Raw materials');

    Livewire::test(InventoryManagement::class)
        ->set('name', 'Cement')
        ->set('category', 'Building materials')
        ->set('batchCategoryId', (string) $category->id)
        ->set('qty', '10')
        ->set('unit', 'bag')
        ->set('unitCost', '5.00')
        ->set('sellingPrice', '8.00')
        ->set('reorderLevel', '2')
        ->call('save')
        ->assertHasNoErrors();

    $item = Inventory::query()->sole();
    expect($item->inventory_batch_category_id)->toBe($category->id)
        ->and($item->batchCategory->name)->toBe('Raw materials');
    Livewire::test(InventoryManagement::class)->assertSee('Raw materials');
    Livewire::test(InventoryOverview::class)->assertSee('Raw materials');

    $category->delete();
    expect($category->fresh()->trashed())->toBeTrue();
    $this->get(route('inventory.management.edit', $item->id))
        ->assertOk()
        ->assertSee('Raw materials (Deleted)');
    Livewire::test(InventoryManagement::class)
        ->call('edit', $item->id)
        ->call('save')
        ->assertHasNoErrors();
    expect($item->fresh()->inventory_batch_category_id)->toBe($category->id);

    Livewire::test(InventoryManagement::class)
        ->call('edit', $item->id)
        ->set('batchCategoryId', (string) $otherCategory->id)
        ->call('save')
        ->assertHasNoErrors();
    expect($item->fresh()->batchCategory->name)->toBe('Finished goods');

    Livewire::test(InventoryManagement::class)
        ->call('edit', $item->id)
        ->set('batchCategoryId', '')
        ->call('save')
        ->assertHasNoErrors();
    expect($item->fresh()->inventory_batch_category_id)->toBeNull()
        ->and($item->fresh()->batchCategory)->toBeNull();
});

test('inventory JSON create and update preserve and clear optional batch category assignments', function () {
    $employee = batchCategoryEmployee();
    $this->actingAs($employee);

    $category = InventoryBatchCategory::create([
        'name' => 'Equipment',
        'description' => null,
        'created_by' => $employee->id,
        'updated_by' => $employee->id,
    ]);
    $attributes = [
        'name' => 'Mixer',
        'category' => 'Equipment',
        'qty' => 2,
        'unit' => 'piece',
        'unit_cost' => 50,
        'selling_price' => 70,
        'reorder_level' => 1,
        'inventory_batch_category_id' => $category->id,
    ];

    $response = $this->postJson(route('inventory.store'), $attributes)
        ->assertCreated()
        ->assertJsonPath('inventory_batch_category_id', $category->id);
    $itemId = $response->json('id');

    $this->putJson(route('inventory.update', $itemId), [
        ...$attributes,
        'inventory_batch_category_id' => null,
    ])->assertOk()
        ->assertJsonPath('inventory.inventory_batch_category_id', null);

    expect(Inventory::findOrFail($itemId)->inventory_batch_category_id)->toBeNull();
});
