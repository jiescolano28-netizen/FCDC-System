<?php

use App\Livewire\DemolitionProjects\ProjectManagement;
use App\Models\DemolitionProject;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function demolitionProjectEmployee(array $permissions = []): Employee
{
    $suffix = Employee::count() + 1;
    $employee = Employee::create([
        'username' => 'demolition.staff.'.$suffix,
        'email' => 'demolition.staff.'.$suffix.'@example.com',
        'password' => Hash::make('employee-password'),
    ]);

    if ($permissions !== []) {
        $role = Role::create(['name' => 'demolition-project-access-'.$suffix, 'guard_name' => 'web']);
        $role->givePermissionTo(collect($permissions)
            ->map(fn (string $name) => Permission::findOrCreate($name, 'web')));
        $employee->assignRole($role);
    }

    return $employee;
}

test('project management creates sequential source codes and updates project details', function () {
    $this->actingAs(demolitionProjectEmployee(['demolition-projects.view', 'demolition-projects.manage']));

    Livewire::test(ProjectManagement::class)
        ->set('name', 'Warehouse removal')
        ->set('location', 'Lot 14, Quezon City')
        ->set('startDate', '2026-11-02')
        ->set('endDate', '')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(ProjectManagement::class)
        ->set('name', 'Old depot clearing')
        ->set('location', 'Makati')
        ->set('startDate', '2026-11-04')
        ->set('endDate', '2026-11-06')
        ->call('save')
        ->assertHasNoErrors();

    $projects = DemolitionProject::orderBy('id')->get();
    expect($projects->pluck('code')->all())->toBe(['DEM-000001', 'DEM-000002'])
        ->and($projects[0]->end_date)->toBeNull();

    Livewire::test(ProjectManagement::class)
        ->call('edit', $projects[0]->id)
        ->set('name', 'Warehouse demolition')
        ->set('location', 'Lot 14, North Quezon City')
        ->set('startDate', '2026-11-03')
        ->set('endDate', '2026-11-20')
        ->call('save')
        ->assertHasNoErrors();

    $projects[0]->refresh();
    expect($projects[0]->code)->toBe('DEM-000001')
        ->and($projects[0]->name)->toBe('Warehouse demolition')
        ->and($projects[0]->location)->toBe('Lot 14, North Quezon City')
        ->and($projects[0]->start_date->toDateString())->toBe('2026-11-03')
        ->and($projects[0]->end_date->toDateString())->toBe('2026-11-20');
});

test('project end date cannot precede its required start date', function () {
    $this->actingAs(demolitionProjectEmployee(['demolition-projects.view', 'demolition-projects.manage']));

    Livewire::test(ProjectManagement::class)
        ->set('name', 'Site clearance')
        ->set('location', 'Cebu City')
        ->set('startDate', '2026-11-10')
        ->set('endDate', '2026-11-09')
        ->call('save')
        ->assertHasErrors(['endDate' => 'after_or_equal']);

    expect(DemolitionProject::count())->toBe(0);
});

test('project source access and management are separate from inventory permissions', function () {
    $employee = demolitionProjectEmployee(['inventory.view', 'inventory.movements.record']);
    $this->actingAs($employee);

    $this->get(route('demolition-projects.index'))->assertForbidden();

    $project = demolitionProjectEmployee(['demolition-projects.view']);
    $this->actingAs($project);
    $this->get(route('demolition-projects.index'))
        ->assertOk()
        ->assertSee('Demolition Projects');

    Livewire::test(ProjectManagement::class)
        ->set('name', 'Blocked edit')
        ->set('location', 'Site')
        ->set('startDate', '2026-11-10')
        ->call('save')
        ->assertForbidden();

    expect(DemolitionProject::count())->toBe(0);
});

test('project manager permission does not grant access to the project source page', function () {
    $this->actingAs(demolitionProjectEmployee(['demolition-projects.manage']));

    $this->get(route('demolition-projects.index'))->assertForbidden();
});
