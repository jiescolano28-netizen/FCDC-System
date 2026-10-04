<?php

namespace App\Support;

class RolePermissionCatalog
{
    public const ADMIN_ROLE = 'System Administrator';

    public static function permissions(): array
    {
        return [
            'Employees' => [
                'employees.view' => 'View employees',
                'employees.create' => 'Create employees',
                'employees.update' => 'Update employees',
                'employees.delete' => 'Delete and restore employees',
                'employees.assign-roles' => 'Assign employee roles',
            ],
            'Roles' => [
                'roles.view' => 'View roles',
                'roles.create' => 'Create roles',
                'roles.update' => 'Update roles and permissions',
                'roles.delete' => 'Delete roles',
            ],
        ];
    }

    public static function names(): array
    {
        return collect(self::permissions())
            ->flatMap(fn (array $permissions) => array_keys($permissions))
            ->all();
    }
}
