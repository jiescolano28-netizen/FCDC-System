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
