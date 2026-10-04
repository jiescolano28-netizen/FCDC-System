<?php

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LoginController;
use App\Livewire\Accounting\AccountingOverview;
use App\Livewire\Accounting\ChartOfAccounts;
use App\Livewire\Accounting\JournalEntry;
use App\Livewire\Dashboard\DashboardPage;
use App\Livewire\Employees\EmployeeManagement;
use App\Livewire\HomePage;
use App\Livewire\Inventory\InventoryManagement;
use App\Livewire\Inventory\InventoryOverview;
use App\Livewire\Roles\RoleManagement;
use App\Livewire\TaxCompliance\VatRecords;
use App\Livewire\Dashboard\ReportsPage;
use App\Livewire\Settings\SettingsPage;
use App\Livewire\TaxCompliance\VatSummary;
use App\Livewire\TaxCompliance\TaxReport;
use Illuminate\Support\Facades\Route;

// HOMEPAGE
Route::get('/', HomePage::class)->name('home');

// LOGIN
Route::get('/login', [LoginController::class, 'showLogin'])
    ->name('login');

Route::post('/login', [LoginController::class, 'login'])
    ->name('login.submit');

// LOGOUT
Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout');

// Authenticated application
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardPage::class)->name('dashboard');
    Route::get('/dashboard/reports', ReportsPage::class)->name('reports');
    Route::get('/accounting', AccountingOverview::class)->name('accounting.overview');
    Route::get('/accounting/chart-of-accounts', ChartOfAccounts::class)->name('accounting.chart-of-accounts');
    Route::get('/accounting/journal-entry', JournalEntry::class)->name('accounting.journal-entry');
    Route::get('/settings', SettingsPage::class)->name('settings');
    Route::get('/pos', \App\Livewire\Pos\PointOfSale::class)->name('pos');
    Route::get('/tax/vat-records', VatRecords::class)->name('tax.vat-records');

    Route::get('/inventory/manage', InventoryManagement::class)->name('inventory.management');
    Route::get('/tax/vat-summary', VatSummary::class)->name('tax.vat-summary');
    Route::get('/tax/report', TaxReport::class)->name('tax.report');
    Route::get('/inventory/overview', InventoryOverview::class)->name('inventory.overview');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::put('/inventory/{inventory}', [InventoryController::class, 'update'])->name('inventory.update');
    Route::delete('/inventory/{inventory}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
    Route::get('/employees', EmployeeManagement::class)
        ->middleware('permission:employees.view')
        ->name('employees.index');
    Route::get('/roles', RoleManagement::class)
        ->middleware('permission:roles.view')
        ->name('roles.index');
});
use App\Livewire\TaxCompliance\VatReturnPreparation;
    Route::get('/tax/vat-return-preparation', VatReturnPreparation::class)->name('tax.vat-return-preparation');
