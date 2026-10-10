<?php

use App\Livewire\Accounting\JournalEntry;
use App\Livewire\Inventory\InventoryManagement;
use App\Livewire\Pos\PointOfSale;
use App\Livewire\Settings\SettingsPage;
use App\Models\Employee;
use App\Models\Inventory;
use App\Support\RolePermissionCatalog;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class)->beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

function modulePermissionEmployee(array $permissions = []): Employee
{
    $employee = Employee::create([
        'username' => 'module.employee',
        'email' => 'module.employee@example.com',
        'password' => Hash::make('employee-password'),
    ]);

    if ($permissions !== []) {
        $role = Role::create(['name' => 'module-access', 'guard_name' => 'web']);
        $role->givePermissionTo(collect($permissions)
            ->map(fn (string $name) => Permission::findOrCreate($name, 'web')));
        $employee->assignRole($role);
    }

    return $employee;
}

test('application modules deny routes unless the employee has the module view permission', function () {
    $routes = [
        ['dashboard', 'dashboard.view'],
        ['reports', 'reports.view'],
        ['inventory.management', 'inventory.view'],
        ['inventory.overview', 'inventory.view'],
        ['pos', 'pos.view'],
        ['tax.vat-records', 'tax.view'],
        ['tax.vat-summary', 'tax.view'],
        ['tax.report', 'tax.view'],
        ['tax.vat-return-preparation', 'tax.view'],
        ['accounting.overview', 'accounting.view'],
        ['accounting.chart-of-accounts', 'accounting.view'],
        ['accounting.opening-books', 'accounting.view'],
        ['accounting.accounts-payable', 'accounting.view'],
        ['accounting.cash-disbursements', 'accounting.view'],
        ['accounting.journal-entry', 'accounting.view'],
        ['accounting.financial-statements', 'accounting.view'],
        ['accounting.general-ledger', 'accounting.view'],
        ['accounting.trial-balance', 'accounting.view'],
        ['settings', 'settings.view'],
    ];

    $employee = modulePermissionEmployee();
    $this->actingAs($employee);

    foreach ($routes as [$route, $permission]) {
        expect($employee->can($permission))->toBeFalse();
        $response = $this->get(route($route));
        expect($response->status())->toBe(403, "Expected {$route} to require {$permission}.");
    }

    $role = Role::create(['name' => 'module-viewer', 'guard_name' => 'web']);
    $role->givePermissionTo(collect(array_unique(array_column($routes, 1)))
        ->map(fn (string $name) => Permission::findOrCreate($name, 'web')));
    $employee->assignRole($role);

    foreach ($routes as [$route, $permission]) {
        $this->get(route($route))->assertOk();
    }
});

test('inventory API separates view from create update and delete permissions', function () {
    $employee = modulePermissionEmployee(['inventory.view']);
    $this->actingAs($employee);

    $this->getJson(route('inventory.index'))->assertOk();
    $this->postJson(route('inventory.store'), [])->assertForbidden();
    $this->putJson(route('inventory.update', 1), [])->assertForbidden();
    $this->deleteJson(route('inventory.destroy', 1))->assertForbidden();
});

test('livewire business actions require their dedicated permissions', function () {
    $this->actingAs(modulePermissionEmployee(['inventory.view', 'pos.view', 'accounting.view', 'settings.view']));

    Livewire::test(InventoryManagement::class)
        ->set('name', 'Cement')
        ->set('category', 'Building Materials')
        ->set('qty', '10')
        ->set('unit', 'bag')
        ->set('unitCost', '5')
        ->call('save')
        ->assertForbidden();
    Livewire::test(InventoryManagement::class)
        ->set('stockItemId', 1)
        ->set('stockQuantity', '2')
        ->set('stockReasonCategory', 'purchase_receipt')
        ->set('stockEffectiveDate', '2026-10-08')
        ->call('recordStockIn')
        ->assertForbidden();

    Livewire::test(PointOfSale::class)
        ->call('checkout')
        ->assertForbidden();

    Livewire::test(JournalEntry::class)
        ->call('addLine')
        ->assertForbidden();

    Livewire::test(SettingsPage::class)
        ->set('companyName', 'Changed Company')
        ->assertForbidden();
});
test('module navigation only links to modules the employee can view', function () {
    $this->actingAs(modulePermissionEmployee(['dashboard.view']));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('dashboard'))
        ->assertDontSee(route('reports'))
        ->assertDontSee(route('inventory.management'))
        ->assertDontSee(route('pos'));
});

test('permission seeding grants the full catalog to administrators but not other roles', function () {
    $administrator = Role::create([
        'name' => RolePermissionCatalog::ADMIN_ROLE,
        'guard_name' => 'web',
    ]);
    $administrator->givePermissionTo(Permission::findOrCreate('employees.view', 'web'));
    $ordinaryRole = Role::create(['name' => 'ordinary-staff', 'guard_name' => 'web']);

    app(RolePermissionSeeder::class)->run();

    expect($administrator->fresh()->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(RolePermissionCatalog::names())
        ->and($ordinaryRole->fresh()->permissions)->toBeEmpty();
});
test('view-only roles see module content without operation controls', function () {
    Inventory::create([
        'name' => 'Cement',
        'category' => 'Building Materials',
        'qty' => 5,
        'unit' => 'bag',
        'unit_cost' => 5,
        'selling_price' => 8,
        'reorder_level' => 2,
    ]);

    $this->actingAs(modulePermissionEmployee([
        'inventory.view',
        'pos.view',
        'accounting.view',
        'settings.view',
    ]));

    Livewire::test(InventoryManagement::class)
        ->assertSee('Cement')
        ->assertDontSee('Add inventory item')
        ->assertDontSee('Receive stock')
        ->assertDontSee('Edit')
        ->assertDontSee('Deactivate');

    Livewire::test(PointOfSale::class)
        ->assertDontSee('Add to Sale')
        ->assertDontSee('Charge');

    Livewire::test(JournalEntry::class)
        ->assertDontSee('Add line')
        ->assertDontSee('Save demonstration entry');

    Livewire::test(SettingsPage::class)
        ->assertSee('disabled', false);
});
