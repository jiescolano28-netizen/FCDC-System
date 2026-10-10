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
                'inventory.valuation.approve' => 'Authorize inventory movement values',
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
                'accounting.create-journal-entry' => 'Prepare manual journal entries',
                'accounting.post-journal-entry' => 'Post manual journal entries and corrections',
                'accounting.maintain-accounts' => 'Create and maintain accounts and posting mappings',
                'accounting.approve-accounts' => 'Approve accounts and posting mappings',
                'accounting.prepare-disbursements' => 'Prepare and maintain direct disbursement drafts',
                'accounting.post-disbursements' => 'Post direct disbursements and corrections',
                'accounting.maintain-suppliers' => 'Maintain supplier identities and details',
                'accounting.prepare-supplier-purchases' => 'Prepare and maintain supplier purchase drafts',
                'accounting.post-supplier-purchases' => 'Post received supplier purchases',
                'accounting.correct-supplier-purchases' => 'Correct posted supplier purchases with reviewed allocations',
                'accounting.post-supplier-refunds' => 'Post actual supplier refund receipts',
                'accounting.maintain-opening-books' => 'Prepare accounting cutover and opening schedules',
                'accounting.close-period' => 'Close reconciled accounting months',
                'accounting.reopen-period' => 'Reopen accounting months and dependent periods',
                'accounting.approve-opening-books' => 'Approve accounting cutover and opening schedules',
                'accounting.reconcile-sources' => 'Reconcile existing post-cutover source activity',
                'accounting.post-reconciled-sources' => 'Post reconciled existing source activity',
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
