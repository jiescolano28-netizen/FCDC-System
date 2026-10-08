<?php
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LoginController;
use App\Livewire\Accounting\AccountingOverview;
use App\Livewire\Accounting\AccountsPayable;
use App\Livewire\Accounting\CashDisbursements;
use App\Livewire\Accounting\ChartOfAccounts;
use App\Livewire\Accounting\FinancialStatements;
use App\Livewire\Accounting\GeneralLedger;
use App\Livewire\Accounting\JournalEntry;
use App\Livewire\Accounting\TrialBalance;
use App\Livewire\Dashboard\DashboardPage;
use App\Livewire\Dashboard\ReportsPage;
use App\Livewire\Employees\EmployeeManagement;
use App\Livewire\HomePage;
use App\Livewire\Inventory\InventoryManagement;
use App\Livewire\Inventory\InventoryOverview;
use App\Livewire\Pos\PointOfSale;
use App\Livewire\Roles\RoleManagement;
use App\Livewire\Settings\SettingsPage;
use App\Livewire\TaxCompliance\TaxReport;
use App\Livewire\TaxCompliance\VatRecords;
use App\Livewire\TaxCompliance\VatReturnPreparation;
use App\Livewire\TaxCompliance\VatSummary;
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
    Route::get('/dashboard', DashboardPage::class)->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('/dashboard/reports', ReportsPage::class)->middleware('permission:reports.view')->name('reports');
    Route::get('/accounting', AccountingOverview::class)->middleware('permission:accounting.view')->name('accounting.overview');
    Route::get('/accounting/chart-of-accounts', ChartOfAccounts::class)->middleware('permission:accounting.view')->name('accounting.chart-of-accounts');
    Route::get('/accounting/accounts-payable', AccountsPayable::class)->middleware('permission:accounting.view')->name('accounting.accounts-payable');
    Route::get('/accounting/cash-disbursements', CashDisbursements::class)->middleware('permission:accounting.view')->name('accounting.cash-disbursements');
    Route::get('/accounting/journal-entry', JournalEntry::class)->middleware('permission:accounting.view')->name('accounting.journal-entry');
    Route::get('/accounting/financial-statements', FinancialStatements::class)->middleware('permission:accounting.view')->name('accounting.financial-statements');
    Route::get('/accounting/general-ledger', GeneralLedger::class)->middleware('permission:accounting.view')->name('accounting.general-ledger');
    Route::get('/accounting/trial-balance', TrialBalance::class)->middleware('permission:accounting.view')->name('accounting.trial-balance');
    Route::get('/settings', SettingsPage::class)->middleware('permission:settings.view')->name('settings');
    Route::get('/pos', PointOfSale::class)->middleware('permission:pos.view')->name('pos');
    Route::get('/tax/vat-records', VatRecords::class)->middleware('permission:tax.view')->name('tax.vat-records');

    Route::get('/inventory/manage', InventoryManagement::class)->middleware('permission:inventory.view')->name('inventory.management');
    Route::get('/tax/vat-summary', VatSummary::class)->middleware('permission:tax.view')->name('tax.vat-summary');
    Route::get('/tax/report', TaxReport::class)->middleware('permission:tax.view')->name('tax.report');
    Route::get('/inventory/overview', InventoryOverview::class)->middleware('permission:inventory.view')->name('inventory.overview');

    Route::get('/inventory', [InventoryController::class, 'index'])
        ->middleware('permission:inventory.view')->name('inventory.index');
    Route::post('/inventory', [InventoryController::class, 'store'])
        ->middleware('permission:inventory.create')->name('inventory.store');
    Route::put('/inventory/{inventory}', [InventoryController::class, 'update'])
        ->middleware('permission:inventory.update')->name('inventory.update');
    Route::delete('/inventory/{inventory}', [InventoryController::class, 'destroy'])
        ->middleware('permission:inventory.delete')->name('inventory.destroy');
    Route::get('/employees', EmployeeManagement::class)
        ->middleware('permission:employees.view')
        ->name('employees.index');
    Route::get('/roles', RoleManagement::class)
        ->middleware('permission:roles.view')
        ->name('roles.index');
    Route::get('/tax/vat-return-preparation', VatReturnPreparation::class)
        ->middleware('permission:tax.view')->name('tax.vat-return-preparation');
});
