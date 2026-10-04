<?php

namespace App\Livewire\Roles;

use App\Support\RolePermissionCatalog;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleManagement extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public array $selectedPermissionNames = [];

    public function edit(int $id): void
    {
        $this->authorizePermission('roles.update');

        $role = $this->findRole($id);
        $this->ensureEditable($role);

        $this->editingId = $role->id;
        $this->name = $role->name;
        $this->selectedPermissionNames = $role->permissions()
            ->whereIn('name', RolePermissionCatalog::names())
            ->pluck('name')
            ->all();
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorizePermission($this->editingId ? 'roles.update' : 'roles.create');

        $role = $this->editingId ? $this->findRole($this->editingId) : null;

        if ($role) {
            $this->ensureEditable($role);
        }

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

        $role ??= new Role(['guard_name' => 'web']);
        $role->name = $validated['name'];
        $role->guard_name = 'web';
        $role->save();
        $role->syncPermissions(
            Permission::where('guard_name', 'web')
                ->whereIn('name', $validated['selectedPermissionNames'] ?? [])
                ->get()
        );

        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorizePermission('roles.delete');

        $role = $this->findRole($id);
        $this->ensureEditable($role);

        if ($role->users()->withTrashed()->exists()) {
            throw ValidationException::withMessages([
                'roles' => 'This role is assigned to employees. Reassign those employees before deleting it.',
            ]);
        }

        $role->delete();
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'selectedPermissionNames']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.roles.role-management', [
            'roles' => Role::with('permissions')
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->get(),
            'permissionGroups' => RolePermissionCatalog::permissions(),
        ])->layout('layouts.app', ['title' => 'Role management']);
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
