<?php

use App\Livewire\DemolitionProjects\ProjectManagement;
use App\Livewire\Inventory\InventoryManagement;
use App\Models\DemolitionProject;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\RecoveredMaterial;
use App\Models\RecoveredMaterialAssessment;
use App\Models\StockMovement;
use App\Services\Inventory\AssessRecoveredMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function recoveredMaterialEmployee(array $permissions = []): Employee
{
    $suffix = Employee::count() + 1;
    $employee = Employee::create([
        'username' => 'recovery-'.$suffix,
        'email' => 'recovery-'.$suffix.'@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    if ($permissions !== []) {
        $role = Role::create(['name' => 'recovery-role-'.$suffix, 'guard_name' => 'web']);
        $role->givePermissionTo(collect($permissions)->map(fn (string $name) => Permission::findOrCreate($name, 'web')));
        $employee->assignRole($role);
    }

    return $employee;
}

function recoveredMaterialProject(): DemolitionProject
{
    return DemolitionProject::create([
        'name' => 'Warehouse demolition',
        'location' => 'Quezon City',
        'start_date' => '2026-10-01',
    ]);
}

function recoveredMaterialInventory(array $overrides = []): Inventory
{
    return Inventory::create(array_merge([
        'name' => 'Recovered timber',
        'category' => 'Lumber',
        'qty' => 2,
        'unit' => 'piece',
        'unit_cost' => 5,
        'selling_price' => 8,
        'reorder_level' => 1,
        'status' => 'active',
    ], $overrides));
}

test('recovered material assessment reconciles quantities and posts only accepted stock to the linked ledger', function () {
    $employee = recoveredMaterialEmployee(['demolition-projects.view', 'demolition-projects.manage']);
    $this->actingAs($employee);
    $project = recoveredMaterialProject();
    $item = recoveredMaterialInventory(['qty' => 2]);

    Livewire::test(ProjectManagement::class)
        ->set('recoveryProjectId', $project->id)
        ->set('recoveryMaterial', 'Salvaged timber')
        ->set('recoveryQuantity', '5.50')
        ->set('recoveryUnit', 'piece')
        ->set('recoveryCondition', 'fair')
        ->set('recoveryNotes', 'Stored under cover')
        ->call('recordRecovery')
        ->assertHasNoErrors();

    $recovery = RecoveredMaterial::firstOrFail();
    Livewire::test(ProjectManagement::class)
        ->call('beginAssessment', $recovery->id)
        ->set('acceptedQuantity', '3.00')
        ->set('rejectedQuantity', '2.50')
        ->set('rejectionReason', '')
        ->set('inventoryId', $item->id)
        ->call('saveAssessment')
        ->assertHasErrors(['rejectionReason']);

    expect(RecoveredMaterialAssessment::count())->toBe(0)
        ->and((float) $item->fresh()->qty)->toBe(2.0);

    Livewire::test(ProjectManagement::class)
        ->call('beginAssessment', $recovery->id)
        ->set('acceptedQuantity', '3.00')
        ->set('rejectedQuantity', '2.50')
        ->set('rejectionReason', 'Warped beyond reuse')
        ->set('inventoryId', $item->id)
        ->call('saveAssessment')
        ->assertHasNoErrors();

    $assessment = RecoveredMaterialAssessment::firstOrFail();
    $movement = StockMovement::findOrFail($assessment->stock_movement_id);
    expect((float) $item->fresh()->qty)->toBe(5.0)
        ->and($assessment->accepted_quantity)->toBe('3.00')
        ->and($assessment->rejected_quantity)->toBe('2.50')
        ->and($movement->type)->toBe('stock_in')
        ->and((float) $movement->quantity)->toBe(3.0)
        ->and($movement->demolition_project_id)->toBe($project->id)
        ->and($movement->recovered_material_id)->toBe($recovery->id)
        ->and($movement->reason_category)->toBe('recovered_material')
        ->and(Inventory::availableForSale()->whereKey($item->id)->exists())->toBeTrue();

    expect(fn () => $recovery->update(['quantity' => 9]))->toThrow(LogicException::class)
        ->and(fn () => $assessment->update(['accepted_quantity' => 4]))->toThrow(LogicException::class)
        ->and(fn () => $assessment->delete())->toThrow(LogicException::class);
});

test('assessment rejects unreconciled quantities and inventory items with a different unit', function () {
    $employee = recoveredMaterialEmployee(['demolition-projects.view', 'demolition-projects.manage']);
    $this->actingAs($employee);
    $project = recoveredMaterialProject();
    $recovery = RecoveredMaterial::create([
        'demolition_project_id' => $project->id,
        'recorded_by' => $employee->id,
        'material' => 'Reclaimed steel',
        'quantity' => 4,
        'unit' => 'kg',
        'condition' => 'good',
    ]);
    $wrongUnit = recoveredMaterialInventory(['unit' => 'piece']);

    Livewire::test(ProjectManagement::class)
        ->call('beginAssessment', $recovery->id)
        ->set('acceptedQuantity', '2')
        ->set('rejectedQuantity', '1')
        ->set('inventoryId', $wrongUnit->id)
        ->call('saveAssessment')
        ->assertHasErrors(['acceptedQuantity']);

    expect(fn () => app(AssessRecoveredMaterial::class)->handle($recovery->id, [
        'accepted_quantity' => '3',
        'rejected_quantity' => '1',
        'inventory_id' => $wrongUnit->id,
    ], $employee->id))->toThrow(ValidationException::class);

    expect(RecoveredMaterialAssessment::count())->toBe(0)
        ->and((float) $wrongUnit->fresh()->qty)->toBe(2.0);
});


test('assessment can create an inventory item with the recovered unit and receive accepted quantity', function () {
    $employee = recoveredMaterialEmployee(['demolition-projects.view', 'demolition-projects.manage']);
    $this->actingAs($employee);
    $project = recoveredMaterialProject();
    $recovery = RecoveredMaterial::create([
        'demolition_project_id' => $project->id,
        'recorded_by' => $employee->id,
        'material' => 'Salvaged copper wire',
        'quantity' => 12.5,
        'unit' => 'meter',
        'condition' => 'fair',
    ]);

    Livewire::test(ProjectManagement::class)
        ->call('beginAssessment', $recovery->id)
        ->set('acceptedQuantity', '10.00')
        ->set('rejectedQuantity', '2.50')
        ->set('rejectionReason', 'Insulation damaged')
        ->set('inventoryChoice', 'new')
        ->set('newInventoryName', 'Recovered copper wire')
        ->set('newInventoryCategory', 'Electrical')
        ->set('newInventoryUnitCost', '4.75')
        ->call('saveAssessment')
        ->assertHasNoErrors();

    $item = Inventory::where('name', 'Recovered copper wire')->firstOrFail();
    expect($item->unit)->toBe('meter')
        ->and($item->category)->toBe('Electrical')
        ->and($item->unit_cost)->toBe('4.75')
        ->and((float) $item->qty)->toBe(10.0)
        ->and(Inventory::availableForSale()->whereKey($item->id)->exists())->toBeFalse();

    $item->update(['selling_price' => 6.5]);
    expect(Inventory::availableForSale()->whereKey($item->id)->exists())->toBeTrue();
});
test('correction reverses the earlier receipt and links the reassessment without editing posted history', function () {
    $employee = recoveredMaterialEmployee(['demolition-projects.view', 'demolition-projects.manage', 'inventory.movements.record']);
    $this->actingAs($employee);
    $project = recoveredMaterialProject();
    $item = recoveredMaterialInventory(['qty' => 0]);
    $recovery = RecoveredMaterial::create([
        'demolition_project_id' => $project->id,
        'recorded_by' => $employee->id,
        'material' => 'Reusable boards',
        'quantity' => 5,
        'unit' => 'piece',
        'condition' => 'good',
    ]);

    Livewire::test(ProjectManagement::class)
        ->call('beginAssessment', $recovery->id)
        ->set('acceptedQuantity', '5')
        ->set('rejectedQuantity', '0')
        ->set('inventoryId', $item->id)
        ->call('saveAssessment')
        ->assertHasNoErrors();

    $original = RecoveredMaterialAssessment::firstOrFail()->stockMovement;
    Livewire::test(ProjectManagement::class)
        ->call('beginAssessment', $recovery->id, true)
        ->set('acceptedQuantity', '3')
        ->set('rejectedQuantity', '2')
        ->set('rejectionReason', 'Two boards cracked on recheck')
        ->set('inventoryId', $item->id)
        ->call('saveAssessment')
        ->assertHasNoErrors();

    $reassessment = RecoveredMaterialAssessment::whereNotNull('supersedes_assessment_id')->firstOrFail();
    $reversal = $original->fresh()->reversal;
    expect((float) $item->fresh()->qty)->toBe(3.0)
        ->and($reversal->type)->toBe('reversal')
        ->and((float) $reversal->quantity)->toBe(-5.0)
        ->and($reassessment->supersedes_assessment_id)->toBe(1)
        ->and((float) $reassessment->stockMovement->quantity)->toBe(3.0)
        ->and(RecoveredMaterialAssessment::count())->toBe(2);

    expect(fn () => app(InventoryManagement::class)->reverseStockMovement($reassessment->stock_movement_id))
        ->toThrow(ValidationException::class);
});

test('recovery management is independent of inventory movement permission', function () {
    $project = recoveredMaterialProject();
    $recovery = RecoveredMaterial::create([
        'demolition_project_id' => $project->id,
        'material' => 'Salvaged brick',
        'quantity' => 10,
        'unit' => 'piece',
        'condition' => 'good',
    ]);
    $inventoryOnly = recoveredMaterialEmployee(['inventory.movements.record']);
    $this->actingAs($inventoryOnly);

    Livewire::test(ProjectManagement::class)->call('beginAssessment', $recovery->id)->assertForbidden();

    expect(RecoveredMaterialAssessment::count())->toBe(0);
});
