<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Support\RolePermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $role = Role::firstOrCreate([
            'name' => RolePermissionCatalog::ADMIN_ROLE,
            'guard_name' => 'web',
        ]);
        $role->syncPermissions(RolePermissionCatalog::names());

        $employee = Employee::firstOrCreate(
            ['username' => 'admin'],
            [
                'email' => 'admin@admin.com',
                'password' => bcrypt('password'),
            ],
        );
        $employee->assignRole($role);
    }
}
