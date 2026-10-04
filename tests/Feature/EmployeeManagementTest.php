<?php

use App\Livewire\Employees\EmployeeManagement;
use App\Models\Employee;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function employeeManagementAdmin(array $permissions = []): Employee
{
    $admin = Employee::create([
        'username' => 'employee.admin',
        'email' => 'employee.admin@example.com',
        'password' => Hash::make('admin-password'),
    ]);

    $role = Role::create(['name' => 'employee-admin', 'guard_name' => 'web']);
    $role->givePermissionTo(collect([
        'employees.view',
        'employees.create',
        'employees.update',
        'employees.delete',
        'employees.assign-roles',
    ])->merge($permissions)->map(
        fn (string $name) => Permission::findOrCreate($name, 'web')
    ));
    $admin->assignRole($role);

    return $admin;
}

test('employee manager creates, updates, soft deletes, and restores employee accounts', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $administrator = employeeManagementAdmin();
    $this->actingAs($administrator);
    $role = Role::create(['name' => 'site-manager', 'guard_name' => 'web']);

    Livewire::test(EmployeeManagement::class)
        ->set('username', 'site.manager')
        ->set('email', 'site.manager@example.com')
        ->set('password', 'initial-secret')
        ->set('selectedRoleIds', [$role->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('site.manager');

    $employee = Employee::where('email', 'site.manager@example.com')->firstOrFail();
    expect(Hash::check('initial-secret', $employee->password))->toBeTrue()
        ->and($employee->hasRole('site-manager'))->toBeTrue();

    Livewire::test(EmployeeManagement::class)
        ->call('edit', $employee->id)
        ->set('username', 'site.lead')
        ->set('password', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($employee->fresh()->username)->toBe('site.lead')
        ->and(Hash::check('initial-secret', $employee->fresh()->password))->toBeTrue();

    Livewire::test(EmployeeManagement::class)
        ->call('delete', $employee->id)
        ->assertDontSee('site.lead');

    expect(Employee::find($employee->id))->toBeNull()
        ->and(Employee::withTrashed()->find($employee->id))->not->toBeNull();

    $this->post(route('logout'));
    $this->post(route('login.submit'), [
        'email' => $employee->email,
        'password' => 'initial-secret',
    ])->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->actingAs($administrator);
    Livewire::test(EmployeeManagement::class)
        ->set('username', 'site.lead')
        ->set('email', 'site.manager@example.com')
        ->set('password', 'replacement-secret')
        ->call('save')
        ->assertHasErrors(['username', 'email']);

    Livewire::test(EmployeeManagement::class)
        ->set('showDeleted', true)
        ->assertSee('site.lead')
        ->call('restore', $employee->id)
        ->assertHasNoErrors();

    expect(Employee::find($employee->id))->not->toBeNull()
        ->and(Employee::find($employee->id)->hasRole('site-manager'))->toBeTrue();
});

test('employees without delete permission cannot reveal soft-deleted employee records', function () {
    $viewer = Employee::create([
        'username' => 'employee.viewer',
        'email' => 'employee.viewer@example.com',
        'password' => Hash::make('viewer-password'),
    ]);
    $permission = Permission::findOrCreate('employees.view', 'web');
    $role = Role::create(['name' => 'employee-viewer', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);
    $viewer->assignRole($role);

    $deleted = Employee::create([
        'username' => 'deleted.private',
        'email' => 'deleted.private@example.com',
        'password' => Hash::make('deleted-password'),
    ]);
    $deleted->delete();

    $this->actingAs($viewer);

    Livewire::test(EmployeeManagement::class)
        ->set('showDeleted', true)
        ->assertDontSee('deleted.private');
});

test('employee manager cannot delete or demote the last administrator', function () {
    $administrator = employeeManagementAdmin();
    $adminRole = Role::create(['name' => \App\Support\RolePermissionCatalog::ADMIN_ROLE, 'guard_name' => 'web']);
    $adminRole->givePermissionTo([
        Permission::findOrCreate('employees.view', 'web'),
        Permission::findOrCreate('employees.update', 'web'),
        Permission::findOrCreate('employees.delete', 'web'),
        Permission::findOrCreate('employees.assign-roles', 'web'),
    ]);
    $administrator->assignRole($adminRole);
    $this->actingAs($administrator);

    Livewire::test(EmployeeManagement::class)
        ->call('delete', $administrator->id)
        ->assertHasErrors();

    Livewire::test(EmployeeManagement::class)
        ->call('edit', $administrator->id)
        ->set('selectedRoleIds', [])
        ->call('save')
        ->assertHasErrors('selectedRoleIds');

    expect(Employee::find($administrator->id))->not->toBeNull()
        ->and($administrator->fresh()->hasRole(\App\Support\RolePermissionCatalog::ADMIN_ROLE))->toBeTrue();
});

test('employee role assignment requires its dedicated permission', function () {
    $admin = employeeManagementAdmin();
    $staffRole = Role::create(['name' => 'staff', 'guard_name' => 'web']);
    $employee = Employee::create([
        'username' => 'staff.member',
        'email' => 'staff.member@example.com',
        'password' => Hash::make('staff-password'),
    ]);
    $adminRole = $admin->roles()->firstOrFail();
    $adminRole->revokePermissionTo('employees.assign-roles');

    $this->actingAs($admin);

    Livewire::test(EmployeeManagement::class)
        ->call('edit', $employee->id)
        ->set('username', 'staff.updated')
        ->set('selectedRoleIds', [$staffRole->id])
        ->call('save')
        ->assertHasNoErrors();

    expect($employee->fresh()->username)->toBe('staff.updated')
        ->and($employee->fresh()->hasRole('staff'))->toBeFalse();
});
