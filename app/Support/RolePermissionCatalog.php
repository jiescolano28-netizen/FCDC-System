<?php

namespace App\Support;

class RolePermissionCatalog
{
    public const ADMIN_ROLE = 'System Administrator';

    public static function permissions(): array
    {
        return [
            'Dashboard' => [
                'dashboard.view' => 'View dashboard',
            ],
            'Reports' => [
                'reports.view' => 'View reports',
            ],
            'Inventory' => [
                'inventory.view' => 'View inventory',
                'inventory.create' => 'Create inventory items',
                'inventory.update' => 'Update inventory items',
                'inventory.delete' => 'Deactivate inventory items',
                'inventory.movements.record' => 'Record inventory movements',
            ],
            'Demolition Projects & Recovery' => [
                'demolition-projects.view' => 'View Demolition Projects',
                'demolition-projects.manage' => 'Manage Demolition Projects and recovery records',
            ],
            'Point of Sale' => [
                'pos.view' => 'View point of sale',
                'pos.checkout' => 'Complete point-of-sale checkouts',
            ],
            'Tax' => [
                'tax.view' => 'View tax compliance',
            ],
            'Accounting' => [
                'accounting.view' => 'View accounting',
                'accounting.create-journal-entry' => 'Create demonstration journal entries',
                'accounting.maintain-accounts' => 'Create and maintain accounts and posting mappings',
                'accounting.approve-accounts' => 'Approve accounts and posting mappings',
            ],
            'Settings' => [
                'settings.view' => 'View company settings',
                'settings.update' => 'Update company settings',
            ],
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
            'Activity Log' => [
                'activity-log.view' => 'View activity log',
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
