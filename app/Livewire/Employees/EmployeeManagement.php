<?php

namespace App\Livewire\Employees;

use App\Models\Employee;
use App\Support\AdministratorAccess;
use App\Support\RolePermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class EmployeeManagement extends Component
{
    public string $search = '';

    public bool $showDeleted = false;

    public ?int $editingId = null;

    public string $username = '';

    public string $email = '';

    public string $password = '';

    public array $selectedRoleIds = [];

    public function edit(int $id): void
    {
        $this->authorizePermission('employees.update');

        $employee = Employee::findOrFail($id);
        $this->editingId = $employee->id;
        $this->username = $employee->username;
        $this->email = $employee->email;
        $this->password = '';
        $this->selectedRoleIds = auth()->user()->can('employees.assign-roles')
            ? $employee->roles()->pluck('roles.id')->map(fn ($id) => (string) $id)->all()
            : [];
        $this->dispatch('employee-modal-open', roleIds: $this->selectedRoleIds);
        $this->resetValidation();
    }

    public function create(): void
    {
        $this->authorizePermission('employees.create');

        $this->resetForm();
        $this->dispatch('employee-modal-open', roleIds: []);
    }

    public function save(): void
    {
        $this->authorizePermission($this->editingId ? 'employees.update' : 'employees.create');

        $employee = $this->editingId ? Employee::findOrFail($this->editingId) : new Employee;
        $canAssignRoles = auth()->user()->can('employees.assign-roles');

        $validated = $this->validate([
            'username' => ['required', 'string', 'max:255', Rule::unique('employees', 'username')->ignore($employee->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($employee->id)],
            'password' => [$employee->exists ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
            ...($canAssignRoles ? [
                'selectedRoleIds' => ['array'],
                'selectedRoleIds.*' => [
                    'integer',
                    Rule::exists('roles', 'id')->where(fn ($query) => $query->where('guard_name', 'web')),
                ],
            ] : []),
        ]);

        $requestedRoleIds = collect($validated['selectedRoleIds'] ?? [])->map(fn ($id) => (int) $id);
        $adminRoleId = Role::where('name', RolePermissionCatalog::ADMIN_ROLE)
            ->where('guard_name', 'web')
            ->value('id');

        DB::transaction(function () use (&$employee, $validated, $canAssignRoles, $requestedRoleIds, $adminRoleId): void {
            if ($employee->exists) {
                $employee = Employee::findOrFail($employee->id);

                if ($employee->hasRole(RolePermissionCatalog::ADMIN_ROLE)) {
                    AdministratorAccess::lockRole();
                }
            }

            if ($canAssignRoles && $requestedRoleIds->contains($adminRoleId)
                && ! $employee->hasRole(RolePermissionCatalog::ADMIN_ROLE)) {
                throw ValidationException::withMessages([
                    'selectedRoleIds' => 'The protected administrator role can only be granted by the provisioning command.',
                ]);
            }

            if ($canAssignRoles && $employee->exists && $employee->hasRole(RolePermissionCatalog::ADMIN_ROLE)
                && ! $requestedRoleIds->contains($adminRoleId)
                && AdministratorAccess::isLastAdministrator($employee)) {
                throw ValidationException::withMessages([
                    'selectedRoleIds' => 'The last administrator cannot lose administrator access.',
                ]);
            }

            $employee->username = $validated['username'];
            $employee->email = $validated['email'];

            if (filled($validated['password'] ?? null)) {
                $employee->password = Hash::make($validated['password']);
            }

            $employee->save();

            if ($canAssignRoles) {
                $employee->syncRoles($requestedRoleIds->all());
            }
        });

        $this->resetForm();
        $this->dispatch('employee-saved', message: $employee->wasRecentlyCreated ? 'Employee created.' : 'Employee updated.');
    }

    public function delete(int $id): void
    {
        $this->authorizePermission('employees.delete');

        DB::transaction(function () use ($id): void {
            $employee = Employee::findOrFail($id);

            if ($employee->hasRole(RolePermissionCatalog::ADMIN_ROLE)) {
                AdministratorAccess::lockRole();

                if (AdministratorAccess::isLastAdministrator($employee)) {
                    throw ValidationException::withMessages([
                        'employees' => 'The last administrator cannot be deleted.',
                    ]);
                }
            }

            $employee->delete();
        });

        $this->resetForm();
        $this->dispatch('employee-deleted');
    }

    public function restore(int $id): void
    {
        $this->authorizePermission('employees.delete');

        Employee::onlyTrashed()->findOrFail($id)->restore();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'username', 'email', 'password', 'selectedRoleIds']);
        $this->resetValidation();
    }

    public function render()
    {
        $showDeleted = $this->showDeleted && auth()->user()->can('employees.delete');
        $employees = ($showDeleted ? Employee::onlyTrashed() : Employee::query())
            ->with('roles')
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('username', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('username')
            ->get();

        return view('livewire.employees.employee-management', [
            'employees' => $employees,
            'roles' => Role::where('guard_name', 'web')
                ->where('name', '!=', RolePermissionCatalog::ADMIN_ROLE)
                ->orderBy('name')
                ->get(),
        ])->layout('layouts.app', ['title' => 'Employee management']);
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
