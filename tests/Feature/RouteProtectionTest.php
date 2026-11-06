<?php

use Illuminate\Support\Facades\Route;

test('every application route requires authentication', function () {
    $publicUris = [
        'login',
        'forgot-password',
        'reset-password',
        'reset-password/{token}',
        'up',
    ];

    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();

        if (in_array($uri, $publicUris, true)) {
            continue;
        }

        if (str_starts_with($uri, 'livewire/') || str_starts_with($uri, 'storage/')) {
            continue;
        }

        $this->assertContains(
            'auth',
            $route->gatherMiddleware(),
            "Route [{$uri}] is missing the auth middleware."
        );
    }
});

test('application modules require their module permission middleware', function () {
    $routes = [
        'home' => null,
        'profile' => null,
        'dashboard' => 'permission:dashboard.view',
        'reports' => 'permission:reports.view',
        'inventory.management' => 'permission:inventory.view',
        'inventory.overview' => 'permission:inventory.view',
        'inventory.batch-categories' => 'permission:inventory.view',
        'inventory.reworking-cost-records' => 'permission:inventory.view',
        'inventory.operating-cost-records' => 'permission:inventory.view',
        'pos' => 'permission:pos.view',
        'tax.vat-records' => 'permission:tax.view',
        'tax.vat-summary' => 'permission:tax.view',
        'tax.report' => 'permission:tax.view',
        'tax.vat-return-preparation' => 'permission:tax.view',
        'accounting.overview' => 'permission:accounting.view',
        'accounting.chart-of-accounts' => 'permission:accounting.view',
        'accounting.opening-books' => 'permission:accounting.view',
        'accounting.supplier-purchases' => 'permission:accounting.view',
        'accounting.accounts-payable' => 'permission:accounting.view',
        'accounting.cash-disbursements' => 'permission:accounting.view',
        'accounting.journal-entry' => 'permission:accounting.view',
        'accounting.financial-statements' => 'permission:accounting.view',
        'accounting.notes-to-financial-statements' => 'permission:accounting.view',
        'accounting.general-ledger' => 'permission:accounting.view',
        'accounting.sales-inventory-profitability' => 'permission:accounting.view',
        'accounting.trial-balance' => 'permission:accounting.view',
        'settings' => 'permission:settings.view',
        'employees.index' => 'permission:employees.view',
        'roles.index' => 'permission:roles.view',
        'activity-log' => 'permission:activity-log.view',
        'demolition-projects.index' => 'permission:demolition-projects.view',
    ];

    foreach ($routes as $name => $permission) {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route, "Route [{$name}] is not registered.");
        $this->assertContains('auth', $route->gatherMiddleware(), "Route [{$name}] is missing auth middleware.");

        if ($permission !== null) {
            $this->assertContains(
                $permission,
                $route->gatherMiddleware(),
                "Route [{$name}] is missing {$permission}."
            );
        }
    }
});

test('guests are redirected to login when manually entering protected URLs', function () {
    $protectedUris = [
        '/',
        '/dashboard',
        '/dashboard/reports',
        '/inventory',
        '/inventory/overview',
        '/inventory/manage',
        '/inventory/manage/create',
        '/inventory/batch-categories',
        '/accounting',
        '/accounting/journal-entry',
        '/accounting/trial-balance',
        '/pos',
        '/tax/vat-records',
        '/settings',
        '/employees',
        '/roles',
        '/activity-log',
        '/demolition-projects',
        '/profile',
    ];

    foreach ($protectedUris as $uri) {
        $this->get($uri)->assertRedirect(route('login'));
    }
});
