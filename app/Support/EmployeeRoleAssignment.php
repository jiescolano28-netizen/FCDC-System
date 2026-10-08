<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class EmployeeRoleAssignment
{
    /** @param array<int, int> $roleIds */
    public static function sync(Employee $employee, array $roleIds): void
    {
        $requestedRoleIds = collect($roleIds)->map(fn ($id) => (int) $id);
        $administratorRoleId = Role::where('name', RolePermissionCatalog::ADMIN_ROLE)
            ->where('guard_name', 'web')
            ->value('id');
        $hasAdministratorRole = $employee->exists && $employee->hasRole(RolePermissionCatalog::ADMIN_ROLE);

        if ($hasAdministratorRole) {
            AdministratorAccess::lockRole();
        }

        if ($requestedRoleIds->contains($administratorRoleId) && ! $hasAdministratorRole) {
            throw ValidationException::withMessages([
                'selectedRoleIds' => 'The protected administrator role can only be granted by the provisioning command.',
            ]);
        }

        if ($hasAdministratorRole && ! $requestedRoleIds->contains($administratorRoleId)
            && AdministratorAccess::isLastAdministrator($employee)) {
            throw ValidationException::withMessages([
                'selectedRoleIds' => 'The last administrator cannot lose administrator access.',
            ]);
        }
        $oldRoleNames = $employee->exists
            ? $employee->roles()->orderBy('name')->pluck('name')->all()
            : [];

        $employee->syncRoles($requestedRoleIds->all());

        $newRoleNames = $employee->roles()->orderBy('name')->pluck('name')->all();

        if ($oldRoleNames !== $newRoleNames) {
            ActivityAudit::recordChange(
                $employee,
                'Employee roles updated',
                ['roles' => $newRoleNames],
                ['roles' => $oldRoleNames],
            );
        }
    }
}
