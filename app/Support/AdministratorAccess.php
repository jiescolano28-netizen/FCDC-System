<?php

namespace App\Support;

use App\Models\Employee;
use Spatie\Permission\Models\Role;

class AdministratorAccess
{
    public static function lockRole(): void
    {
        Role::where('name', RolePermissionCatalog::ADMIN_ROLE)
            ->where('guard_name', 'web')
            ->lockForUpdate()
            ->firstOrFail();
    }

    public static function activeCount(): int
    {
        return Employee::role(RolePermissionCatalog::ADMIN_ROLE)
            ->lockForUpdate()
            ->count();
    }

    public static function isLastAdministrator(Employee $employee): bool
    {
        return $employee->hasRole(RolePermissionCatalog::ADMIN_ROLE)
            && self::activeCount() <= 1;
    }
}
