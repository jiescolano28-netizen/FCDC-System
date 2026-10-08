<?php

namespace App\Livewire\Roles;

use App\Models\Employee;
use App\Support\ActivityAudit;
use App\Support\EmployeeRoleAssignment;
use App\Support\RolePermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class RoleManagement extends Component
{
    public ?int $selectedRoleId = null;

    public ?int $editingId = null;

    public string $name = '';

    public string $activeTab = 'permissions';

    public string $userSearch = '';

    public array $selectedPermissionNames = [];

    public array $employeeRoleSelections = [];
    public function setActiveTab(string $tab): void
    {
        if ($tab === 'users') {
            $this->authorizePermission('employees.assign-roles');
        } elseif ($tab !== 'permissions') {
            throw ValidationException::withMessages(['roles' => 'Unknown role management view.']);
        }

        $this->activeTab = $tab;
    }


    public function mount(): void
    {
        $firstRole = Role::where('guard_name', 'web')->orderBy('name')->first();

        if ($firstRole) {
            $this->selectRole($firstRole->id);
        }
    }

    public function selectRole(int $id): void
    {
        $this->authorizePermission('roles.view');
        $role = $this->findRole($id);
        $this->selectedRoleId = $role->id;
        $this->editingId = null;
        $this->name = $role->name;
        $this->selectedPermissionNames = $role->permissions()
            ->whereIn('name', RolePermissionCatalog::names())
            ->pluck('name')
            ->all();
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $this->authorizePermission('roles.update');
        $role = $this->findRole($id);
        $this->ensureEditable($role);
        $this->selectRole($id);
        $this->editingId = $id;
    }

    public function saveSelected(): void
    {
        if ($this->selectedRoleId) {
            $this->authorizePermission('roles.update');
            $this->editingId = $this->selectedRoleId;
        }

        $this->save();
    }
    public function startCreate(): void
    {
        $this->authorizePermission('roles.create');
        $this->activeTab = 'permissions';
        $this->reset(['selectedRoleId', 'editingId', 'name', 'selectedPermissionNames']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorizePermission($this->editingId ? 'roles.update' : 'roles.create');

        $role = $this->editingId ? $this->findRole($this->editingId) : null;

        if ($role) {
            $this->ensureEditable($role);
        }

        try {
            $validated = $this->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::notIn([RolePermissionCatalog::ADMIN_ROLE]),
                    Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role?->id),
                ],
                'selectedPermissionNames' => ['array'],
                'selectedPermissionNames.*' => [
                    'string',
                    Rule::in(RolePermissionCatalog::names()),
                ],
            ]);
        } catch (ValidationException $exception) {
            $this->dispatch('role-error', message: 'Check the role name and selected permissions.');
            throw $exception;
        }

        $isNew = $role === null;
        $role ??= new Role(['guard_name' => 'web']);
        $oldName = $role->exists ? $role->name : null;
        $oldPermissions = $role->exists
            ? $role->permissions()->orderBy('name')->pluck('name')->all()
            : [];

        $role = DB::transaction(function () use ($role, $isNew, $oldName, $oldPermissions, $validated): Role {
            $role->name = $validated['name'];
            $role->guard_name = 'web';
            $role->save();

            $permissions = Permission::where('guard_name', 'web')
                ->whereIn('name', $validated['selectedPermissionNames'] ?? [])
                ->get();
            $role->syncPermissions($permissions);

            $newPermissions = $role->permissions()->orderBy('name')->pluck('name')->all();
            $attributes = $isNew ? [
                'name' => $role->name,
                'permissions' => $newPermissions,
            ] : [];
            $old = [];

            if (! $isNew && $oldName !== $role->name) {
                $attributes['name'] = $role->name;
                $old['name'] = $oldName;
            }

            if (! $isNew && $oldPermissions !== $newPermissions) {
                $attributes['permissions'] = $newPermissions;
                $old['permissions'] = $oldPermissions;
            }

            ActivityAudit::recordChange(
                $role,
                $isNew ? 'Role created' : 'Role updated',
                $attributes,
                $old,
            );

            return $role;
        });

        $this->selectedRoleId = $role->id;
        $this->editingId = $role->id;
        $this->name = $role->name;
        $this->selectedPermissionNames = $role->permissions()->pluck('name')->all();
        $this->resetValidation();
        $this->dispatch('role-saved', message: $isNew ? 'Role created successfully.' : 'Role changes saved.');
    }

    public function duplicateRole(): void
    {
        $this->authorizePermission('roles.create');
        $source = $this->selectedRole();

        if (! $source) {
            throw ValidationException::withMessages(['roles' => 'Select a role to duplicate.']);
        }

        try {
            $this->ensureEditable($source);
        } catch (ValidationException $exception) {
            $this->dispatch('role-error', message: 'The protected administrator role cannot be duplicated.');
            throw $exception;
        }

        $baseName = 'Copy of '.$source->name;
        $name = $baseName;
        $suffix = 2;

        while (Role::where('guard_name', 'web')->where('name', $name)->exists()) {
            $name = $baseName.' ('.$suffix++.')';
        }

        $copy = DB::transaction(function () use ($source, $name): Role {
            $copy = Role::create(['name' => $name, 'guard_name' => 'web']);
            $copy->syncPermissions($source->permissions);
            $permissions = $copy->permissions()->orderBy('name')->pluck('name')->all();

            ActivityAudit::recordChange(
                $copy,
                'Role duplicated',
                ['name' => $copy->name, 'permissions' => $permissions],
            );

            return $copy;
        });

        $this->selectRole($copy->id);
        $this->dispatch('role-saved', message: 'Role duplicated successfully.');
    }

    public function toggleModule(string $module): void
    {
        $this->authorizePermissionChanges();
        $modulePermissions = RolePermissionCatalog::permissions()[$module] ?? [];
        $names = array_keys($modulePermissions);
        $enable = count(array_intersect($names, $this->selectedPermissionNames)) !== count($names);

        $this->selectedPermissionNames = array_values(array_unique(array_merge(
            array_diff($this->selectedPermissionNames, $names),
            $enable ? $names : [],
        )));
    }

    public function toggleColumn(string $column): void
    {
        $this->authorizePermissionChanges();
        $groups = RolePermissionCatalog::permissions();
        $names = [];

        foreach ($groups as $permissions) {
            foreach (array_keys($permissions) as $name) {
                if ($this->permissionColumn($name) === $column) {
                    $names[] = $name;
                }
            }
        }

        $enable = count(array_intersect($names, $this->selectedPermissionNames)) !== count($names);
        $this->selectedPermissionNames = array_values(array_unique(array_merge(
            array_diff($this->selectedPermissionNames, $names),
            $enable ? $names : [],
        )));
    }

    public function applyPreset(string $preset): void
    {
        $this->authorizePermissionChanges();

        $allNames = RolePermissionCatalog::names();
        $this->selectedPermissionNames = match ($preset) {
            'full' => $allNames,
            'read' => array_values(array_filter($allNames, fn (string $name) => $this->permissionColumn($name) === 'view')),
            'clear' => [],
            default => throw ValidationException::withMessages(['roles' => 'Unknown permission preset.']),
        };
    }

    public function assignEmployeeRoles(int $employeeId): void
    {
        $this->authorizePermission('employees.assign-roles');

        $validated = validator(
            ['roleIds' => $this->employeeRoleSelections[$employeeId] ?? []],
            [
                'roleIds' => ['array'],
                'roleIds.*' => [
                    'integer',
                    Rule::exists('roles', 'id')->where(fn ($query) => $query
                        ->where('guard_name', 'web')
                        ->where('name', '!=', RolePermissionCatalog::ADMIN_ROLE)),
                ],
            ],
        )->validate();

        try {
            DB::transaction(function () use ($employeeId, $validated): void {
                $employee = Employee::whereKey($employeeId)->lockForUpdate()->firstOrFail();
                $roleIds = array_map('intval', $validated['roleIds']);
                $administratorRoleId = Role::where('guard_name', 'web')
                    ->where('name', RolePermissionCatalog::ADMIN_ROLE)
                    ->value('id');

                if ($employee->hasRole(RolePermissionCatalog::ADMIN_ROLE) && $administratorRoleId) {
                    $roleIds[] = (int) $administratorRoleId;
                }

                EmployeeRoleAssignment::sync($employee, array_values(array_unique($roleIds)));
            });
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Unable to update employee roles.';
            $this->dispatch('role-error', message: $message);
            throw $exception;
        }

        $this->dispatch('role-assignment-saved', message: 'Employee roles updated.');
    }

    public function delete(int $id): void
    {
        $this->authorizePermission('roles.delete');

        try {
            $role = $this->findRole($id);
            $this->ensureEditable($role);

            if ($role->users()->withTrashed()->exists()) {
                throw ValidationException::withMessages([
                    'roles' => 'This role is assigned to employees. Reassign those employees before deleting it.',
                ]);
            }

            DB::transaction(function () use ($role): void {
                $old = [
                    'name' => $role->name,
                    'permissions' => $role->permissions()->orderBy('name')->pluck('name')->all(),
                ];
                $role->delete();

                ActivityAudit::recordChange($role, 'Role deleted', [], $old);
            });
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Unable to delete this role.';
            $this->dispatch('role-error', message: $message);
            throw $exception;
        }

        $nextRole = Role::where('guard_name', 'web')->orderBy('name')->first();
        if ($nextRole) {
            $this->selectRole($nextRole->id);
        } else {
            $this->activeTab = 'permissions';
            $this->reset(['selectedRoleId', 'editingId', 'name', 'selectedPermissionNames']);
        }
        $this->dispatch('role-saved', message: 'Role deleted.');
    }

    public function resetForm(): void
    {
        $this->reset(['selectedRoleId', 'editingId', 'name', 'selectedPermissionNames']);
        $this->resetValidation();
    }

    public function render()
    {
        $groups = RolePermissionCatalog::permissions();
        $permissionMatrix = collect($groups)->map(function (array $permissions, string $module): array {
            return [
                'module' => $module,
                'permissions' => collect($permissions)->map(fn (string $label, string $name): array => [
                    'name' => $name,
                    'label' => $label,
                    'column' => $this->permissionColumn($name),
                ])->values(),
            ];
        })->values();
        $columnOrder = ['view', 'create', 'edit', 'delete', 'export', 'other'];
        $columnLabels = [
            'view' => 'View',
            'create' => 'Create',
            'edit' => 'Edit',
            'delete' => 'Delete',
            'export' => 'Export',
            'other' => 'Other permissions',
        ];
        $presentColumns = $permissionMatrix->flatMap(fn (array $module) => $module['permissions']->pluck('column'))->unique();
        $permissionColumns = collect($columnOrder)
            ->filter(fn (string $column) => $presentColumns->contains($column))
            ->map(fn (string $column) => ['key' => $column, 'label' => $columnLabels[$column]])
            ->values();
        $roles = Role::with('permissions')->withCount('users')
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get();
        $employees = collect();

        if (auth()->user()->can('employees.assign-roles')) {
            $employees = Employee::with('roles')
                ->when($this->userSearch !== '', function ($query): void {
                    $query->where(fn ($query) => $query
                        ->where('username', 'like', '%'.$this->userSearch.'%')
                        ->orWhere('email', 'like', '%'.$this->userSearch.'%'));
                })
                ->orderBy('username')
                ->get();

            $assignableRoles = $roles->reject(fn (Role $role) => $role->name === RolePermissionCatalog::ADMIN_ROLE);
            foreach ($employees as $employee) {
                $this->employeeRoleSelections[$employee->id] ??= $employee->roles
                    ->reject(fn (Role $role) => $role->name === RolePermissionCatalog::ADMIN_ROLE)
                    ->pluck('id')->map(fn ($id) => (string) $id)->all();
            }
        } else {
            $assignableRoles = collect();
        }

        return view('livewire.roles.role-management', [
            'roles' => $roles,
            'selectedRole' => $roles->firstWhere('id', $this->selectedRoleId),
            'permissionGroups' => $groups,
            'permissionMatrix' => $permissionMatrix,
            'permissionColumns' => $permissionColumns,
            'employees' => $employees,
            'assignableRoles' => $assignableRoles,
        ])->layout('layouts.app', ['title' => 'Role management']);
    }

    private function permissionColumn(string $name): string
    {
        return match (true) {
            str_ends_with($name, '.view') => 'view',
            str_ends_with($name, '.create') => 'create',
            str_ends_with($name, '.update') => 'edit',
            str_ends_with($name, '.delete') => 'delete',
            str_ends_with($name, '.export') => 'export',
            default => 'other',
        };
    }

    private function selectedRole(): ?Role
    {
        return $this->selectedRoleId ? $this->findRole($this->selectedRoleId) : null;
    }

    private function authorizePermissionChanges(): void
    {
        if ($this->selectedRoleId) {
            $this->authorizePermission('roles.update');
            $this->editableSelectedRole();

            return;
        }

        $this->authorizePermission('roles.create');
    }

    private function editableSelectedRole(): Role
    {
        $role = $this->selectedRole() ?? throw ValidationException::withMessages(['roles' => 'Select a role first.']);
        $this->ensureEditable($role);

        return $role;
    }

    private function findRole(int $id): Role
    {
        return Role::where('guard_name', 'web')->findOrFail($id);
    }

    private function ensureEditable(Role $role): void
    {
        if ($role->name === RolePermissionCatalog::ADMIN_ROLE) {
            throw ValidationException::withMessages([
                'roles' => 'The protected administrator role cannot be changed here.',
            ]);
        }
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
