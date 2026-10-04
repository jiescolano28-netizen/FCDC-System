<?php

use App\Models\Employee;
use App\Support\RolePermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('interactive command provisions a dedicated protected administrator only once', function () {
    $existingEmployee = Employee::create([
        'username' => 'existing.employee',
        'email' => 'existing.employee@example.com',
        'password' => Hash::make('employee-password'),
    ]);

    $this->artisan('app:provision-administrator')
        ->expectsQuestion('Username', 'initial.admin')
        ->expectsQuestion('Email', 'initial.admin@example.com')
        ->expectsQuestion('Password', 'correct-horse-battery')
        ->expectsQuestion('Confirm password', 'correct-horse-battery')
        ->expectsOutput('Administrator employee provisioned successfully.')
        ->assertExitCode(0);

    $administrator = Employee::where('email', 'initial.admin@example.com')->firstOrFail();
    expect(Hash::check('correct-horse-battery', $administrator->password))->toBeTrue()
        ->and($administrator->hasRole(RolePermissionCatalog::ADMIN_ROLE))->toBeTrue()
        ->and($existingEmployee->fresh()->hasRole(RolePermissionCatalog::ADMIN_ROLE))->toBeFalse();

    $adminRole = Role::where('name', RolePermissionCatalog::ADMIN_ROLE)->firstOrFail();
    expect($adminRole->hasPermissionTo('employees.assign-roles'))->toBeTrue()
        ->and($adminRole->hasPermissionTo('roles.delete'))->toBeTrue();

    $this->artisan('app:provision-administrator')
        ->expectsOutput('An administrator already exists.')
        ->assertExitCode(1);
});

test('provisioning rejects a weak or mismatched password without creating an administrator', function () {
    $this->artisan('app:provision-administrator')
        ->expectsQuestion('Username', 'initial.admin')
        ->expectsQuestion('Email', 'initial.admin@example.com')
        ->expectsQuestion('Password', 'short')
        ->expectsQuestion('Confirm password', 'not-the-same')
        ->assertExitCode(1);

    expect(Employee::count())->toBe(0)
        ->and(Role::where('name', RolePermissionCatalog::ADMIN_ROLE)->exists())->toBeFalse();
});
