<?php

use App\Livewire\ActivityLog\ActivityLogPage;
use App\Livewire\Employees\EmployeeManagement;
use App\Models\Employee;
use App\Models\Inventory;
use App\Support\EmployeeRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

function activityLogTestEmployee(string $username, array $permissions = []): Employee
{
    $employee = Employee::create([
        'username' => $username,
        'email' => $username.'@example.test',
        'password' => Hash::make('secret-password'),
    ]);

    if ($permissions !== []) {
        grantEmployeeTestPermissions($employee, $permissions);
    }

    return $employee;
}

test('activity log route and sidebar require the dedicated view permission', function () {
    $employee = activityLogTestEmployee('log-reader-denied');
    $this->actingAs($employee)
        ->get(route('activity-log'))
        ->assertForbidden();

    $reader = activityLogTestEmployee('log-reader', ['activity-log.view']);
    $this->actingAs($reader)
        ->get(route('activity-log'))
        ->assertOk()
        ->assertSee('Activity log')
        ->assertSee(route('activity-log'));

    Livewire::actingAs($employee)
        ->test(ActivityLogPage::class)
        ->assertForbidden();
});

test('inventory API changes create globally visible activity with safe field diffs', function () {
    $employee = activityLogTestEmployee('inventory-auditor', [
        'inventory.create',
        'inventory.update',
        'inventory.delete',
        'activity-log.view',
    ]);
    $this->actingAs($employee);

    $response = $this->postJson(route('inventory.store'), [
        'name' => 'Audit gravel',
        'category' => 'Aggregate',
        'qty' => 12,
        'unit' => 'bag',
        'unit_cost' => 8.5,
        'selling_price' => 10,
        'reorder_level' => 3,
    ])->assertCreated();

    $inventoryId = $response->json('id');
    $created = Activity::query()->where('subject_type', Inventory::class)->where('subject_id', $inventoryId)->firstOrFail();
    expect($created->causer_id)->toBe($employee->id)
        ->and($created->properties['attributes']['name'])->toBe('Audit gravel');

    $this->putJson(route('inventory.update', $inventoryId), [
        'name' => 'Washed gravel',
        'category' => 'Aggregate',
        'qty' => 10,
        'unit' => 'bag',
        'unit_cost' => 9,
        'selling_price' => 11,
        'reorder_level' => 3,
    ])->assertOk();

    $updated = Activity::query()->where('subject_type', Inventory::class)
        ->where('subject_id', $inventoryId)->where('event', 'updated')->firstOrFail();
    expect($updated->properties['old']['name'])->toBe('Audit gravel')
        ->and($updated->properties['attributes']['name'])->toBe('Washed gravel');

    $this->deleteJson(route('inventory.destroy', $inventoryId))->assertOk();
    expect(Activity::query()->where('subject_type', Inventory::class)
        ->where('subject_id', $inventoryId)->where('event', 'deleted')->exists())->toBeTrue();
});

test('employee changes exclude password values and role assignments record role diffs', function () {
    $actor = activityLogTestEmployee('employee-auditor', [
        'activity-log.view',
        'employees.view',
        'employees.delete',
    ]);
    $this->actingAs($actor);

    $employee = Employee::create([
        'username' => 'staff-before',
        'email' => 'before@example.test',
        'password' => Hash::make('a-private-password'),
    ]);
    $created = Activity::query()->where('subject_type', Employee::class)
        ->where('subject_id', $employee->id)->where('event', 'created')->firstOrFail();
    expect($created->properties['attributes'])->toHaveKeys(['username', 'email'])
        ->and($created->properties['attributes'])->not->toHaveKey('password')
        ->and(json_encode($created->properties))->not->toContain('a-private-password');

    $employee->username = 'staff-after';
    $employee->email = 'after@example.test';
    $employee->password = Hash::make('another-private-password');
    $employee->save();

    $updated = Activity::query()->where('subject_type', Employee::class)
        ->where('subject_id', $employee->id)->where('event', 'updated')->firstOrFail();
    expect($updated->properties['old']['username'])->toBe('staff-before')
        ->and($updated->properties['attributes']['email'])->toBe('after@example.test')
        ->and(json_encode($updated->properties))->not->toContain('another-private-password');

    $role = Role::create(['name' => 'audited-inventory-viewer', 'guard_name' => 'web']);
    EmployeeRoleAssignment::sync($employee, [$role->id]);
    $assignment = Activity::query()->where('subject_type', Employee::class)
        ->where('subject_id', $employee->id)->where('description', 'Employee roles updated')->firstOrFail();
    expect($assignment->properties['old']['roles'])->toBe([])
        ->and($assignment->properties['attributes']['roles'])->toBe([$role->name]);


    $employee->delete();
    Livewire::test(EmployeeManagement::class)
        ->call('restore', $employee->id)
        ->assertHasNoErrors();

    $restored = Activity::query()->where('subject_type', Employee::class)
        ->where('subject_id', $employee->id)->where('description', 'Employee restored')->firstOrFail();
    expect($restored->properties['old']['deleted_at'])->not->toBeNull()
        ->and($restored->properties['attributes']['deleted_at'])->toBeNull();
});

test('role creation, permission updates and deletion are recorded', function () {
    $actor = activityLogTestEmployee('role-auditor', [
        'roles.view',
        'roles.create',
        'roles.update',
        'roles.delete',
        'activity-log.view',
    ]);
    $this->actingAs($actor);
    \Spatie\Permission\Models\Permission::findOrCreate('inventory.view', 'web');

    Livewire::test(\App\Livewire\Roles\RoleManagement::class)
        ->set('name', 'Audited inventory role')
        ->set('selectedPermissionNames', ['inventory.view'])
        ->call('save')
        ->assertHasNoErrors();

    $role = Role::where('name', 'Audited inventory role')->firstOrFail();
    expect(Activity::query()->where('subject_type', Role::class)
        ->where('subject_id', $role->id)->where('description', 'Role created')->exists())->toBeTrue();

    Livewire::test(\App\Livewire\Roles\RoleManagement::class)
        ->set('editingId', $role->id)
        ->set('name', 'Audited stock role')
        ->set('selectedPermissionNames', [])
        ->call('save')
        ->assertHasNoErrors();

    $updated = Activity::query()->where('subject_type', Role::class)
        ->where('subject_id', $role->id)->where('description', 'Role updated')->firstOrFail();
    expect($updated->properties['old']['name'])->toBe('Audited inventory role')
        ->and($updated->properties['attributes']['name'])->toBe('Audited stock role')
        ->and($updated->properties['old']['permissions'])->toBe(['inventory.view'])
        ->and($updated->properties['attributes']['permissions'])->toBe([]);

    Livewire::test(\App\Livewire\Roles\RoleManagement::class)
        ->call('delete', $role->id)
        ->assertHasNoErrors();

    expect(Activity::query()->where('subject_type', Role::class)
        ->where('subject_id', $role->id)->where('description', 'Role deleted')->exists())->toBeTrue();
});
