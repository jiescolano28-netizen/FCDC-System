<?php

namespace Database\Seeders;

use App\Support\RolePermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RolePermissionCatalog::names() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $administratorRole = Role::where('name', RolePermissionCatalog::ADMIN_ROLE)
            ->where('guard_name', 'web')
            ->first();

        if ($administratorRole) {
            $administratorRole->syncPermissions(RolePermissionCatalog::names());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
