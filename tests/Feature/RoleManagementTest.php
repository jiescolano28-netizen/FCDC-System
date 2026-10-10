<?php

use App\Livewire\Roles\RoleManagement;
use App\Models\Employee;
use App\Support\RolePermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function roleManagementAdmin(): Employee
{
    $admin = Employee::create([
        'username' => 'role.admin',
        'email' => 'role.admin@example.com',
        'password' => Hash::make('admin-password'),
    ]);

    $role = Role::create(['name' => 'role-manager', 'guard_name' => 'web']);
    $role->givePermissionTo([
        Permission::findOrCreate('roles.view', 'web'),
        Permission::findOrCreate('roles.create', 'web'),
        Permission::findOrCreate('roles.update', 'web'),
        Permission::findOrCreate('roles.delete', 'web'),
    ]);
    $admin->assignRole($role);

    return $admin;
}

test('role manager creates roles with catalog permissions and blocks deleting assigned roles', function () {
    $this->actingAs(roleManagementAdmin());
    $permission = Permission::findOrCreate('employees.view', 'web');

    Livewire::test(RoleManagement::class)
        ->set('name', 'Read only staff')
        ->set('selectedPermissionNames', [$permission->name])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Read only staff');

    $role = Role::where('name', 'Read only staff')->firstOrFail();
    expect($role->hasPermissionTo('employees.view'))->toBeTrue();

    $employee = Employee::create([
        'username' => 'assigned.staff',
        'email' => 'assigned.staff@example.com',
        'password' => Hash::make('staff-password'),
    ]);
    $employee->assignRole($role);

    Livewire::test(RoleManagement::class)
        ->call('delete', $role->id)
        ->assertHasErrors();

    $employee->delete();

    Livewire::test(RoleManagement::class)
        ->call('delete', $role->id)
        ->assertHasErrors();

    expect(Role::find($role->id))->not->toBeNull();
});

test('protected administrator role cannot be edited or deleted through role management', function () {
    $admin = roleManagementAdmin();
    $protectedRole = Role::create([
        'name' => RolePermissionCatalog::ADMIN_ROLE,
        'guard_name' => 'web',
    ]);
    $protectedRole->givePermissionTo(Permission::findOrCreate('roles.update', 'web'));
    $admin->assignRole($protectedRole);
    $this->actingAs($admin);

    Livewire::test(RoleManagement::class)
        ->call('edit', $protectedRole->id)
        ->assertHasErrors();

    Livewire::test(RoleManagement::class)
        ->call('delete', $protectedRole->id)
        ->assertHasErrors();

    expect(Role::find($protectedRole->id))->not->toBeNull();
});

test('employees without role view permission cannot open role management', function () {
    $employee = Employee::create([
        'username' => 'unprivileged',
        'email' => 'unprivileged@example.com',
        'password' => Hash::make('employee-password'),
    ]);

    $this->actingAs($employee)->get(route('roles.index'))->assertForbidden();
});

test('role manager saves individually selected permissions when updating a role', function () {
    app(\Database\Seeders\RolePermissionSeeder::class)->run();
    $this->actingAs(roleManagementAdmin());
    $role = Role::create(['name' => 'inventory-reader', 'guard_name' => 'web']);
    $role->givePermissionTo(Permission::findOrCreate('inventory.view', 'web'));

    Livewire::test(RoleManagement::class)
        ->call('selectRole', $role->id)
        ->set('selectedPermissionNames', ['inventory.view', 'inventory.update'])
        ->call('saveSelected')
        ->assertHasNoErrors();

    expect($role->fresh()->permissions()->pluck('name')->all())
        ->toEqualCanonicalizing(['inventory.view', 'inventory.update']);
});

test('role manager persists permission presets and duplicates saved roles', function () {
    app(\Database\Seeders\RolePermissionSeeder::class)->run();
    $this->actingAs(roleManagementAdmin());
    $role = Role::create(['name' => 'reviewer', 'guard_name' => 'web']);
    $role->givePermissionTo(Permission::findOrCreate('employees.view', 'web'));

    Livewire::test(RoleManagement::class)
        ->call('edit', $role->id)
        ->set('name', 'Read-only reviewer')
        ->call('applyPreset', 'read')
        ->call('saveSelected')
        ->assertHasNoErrors();

    $readPermissions = array_values(array_filter(
        RolePermissionCatalog::names(),
        fn (string $name) => str_ends_with($name, '.view'),
    ));
    $role->refresh();
    expect($role->name)->toBe('Read-only reviewer')
        ->and($role->permissions()->pluck('name')->all())->toEqualCanonicalizing($readPermissions);

    Livewire::test(RoleManagement::class)
        ->call('selectRole', $role->id)
        ->call('duplicateRole')
        ->assertHasNoErrors()
        ->assertSee('Copy of Read-only reviewer');

    $copy = Role::where('name', 'Copy of Read-only reviewer')->firstOrFail();
    expect($copy->permissions()->pluck('name')->all())->toEqualCanonicalizing($readPermissions);
});

test('role manager persists multiple employee roles through role assignments', function () {
    $admin = roleManagementAdmin();
    $admin->roles()->firstOrFail()->givePermissionTo(Permission::findOrCreate('employees.assign-roles', 'web'));
    $this->actingAs($admin);

    $firstRole = Role::create(['name' => 'project-reader', 'guard_name' => 'web']);
    $secondRole = Role::create(['name' => 'project-editor', 'guard_name' => 'web']);
    $employee = Employee::create([
        'username' => 'multi-role.staff',
        'email' => 'multi-role.staff@example.com',
        'password' => Hash::make('staff-password'),
    ]);
    $administratorRole = Role::create([
        'name' => RolePermissionCatalog::ADMIN_ROLE,
        'guard_name' => 'web',
    ]);
    $employee->assignRole($administratorRole);

    Livewire::test(RoleManagement::class)
        ->set('employeeRoleSelections.'.$employee->id, [(string) $firstRole->id, (string) $secondRole->id])
        ->call('assignEmployeeRoles', $employee->id)
        ->assertHasNoErrors();

    expect($employee->fresh()->roles()->pluck('name')->all())
        ->toEqualCanonicalizing([
            RolePermissionCatalog::ADMIN_ROLE,
            'project-reader',
            'project-editor',
        ]);
});
