<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Support\AdministratorAccess;
use App\Support\RolePermissionCatalog;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ProvisionAdministrator extends Command
{
    protected $signature = 'app:provision-administrator';

    protected $description = 'Create the first employee with protected administrator access';

    public function handle(): int
    {
        $adminRoleName = RolePermissionCatalog::ADMIN_ROLE;

        if (Role::where('name', $adminRoleName)
            ->where('guard_name', 'web')
            ->whereHas('users')
            ->exists()) {
            $this->error('An administrator already exists.');

            return self::FAILURE;
        }

        $username = $this->ask('Username');
        $email = $this->ask('Email');
        $password = $this->secret('Password');
        $passwordConfirmation = $this->secret('Confirm password');

        $validator = Validator::make([
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'username' => ['required', 'string', 'max:255', 'unique:employees,username'],
            'email' => ['required', 'email', 'max:255', 'unique:employees,email'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        app(RolePermissionSeeder::class)->run();

        $provisioned = DB::transaction(function () use ($username, $email, $password, $adminRoleName): bool {
            $role = Role::firstOrCreate([
                'name' => $adminRoleName,
                'guard_name' => 'web',
            ]);
            $role = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();

            if (AdministratorAccess::activeCount() > 0) {
                return false;
            }

            $role->syncPermissions(RolePermissionCatalog::names());

            $employee = Employee::create([
                'username' => $username,
                'email' => $email,
                'password' => Hash::make($password),
            ]);
            $employee->assignRole($role);

            return true;
        });

        if (! $provisioned) {
            $this->error('An administrator already exists.');

            return self::FAILURE;
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->info('Administrator employee provisioned successfully.');

        return self::SUCCESS;
    }
}
