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
    Route::get('/dashboard', DashboardPage::class)->name('dashboard');
    Route::get('/dashboard/reports', ReportsPage::class)->name('reports');
    Route::get('/accounting', AccountingOverview::class)->name('accounting.overview');
    Route::get('/accounting/chart-of-accounts', ChartOfAccounts::class)->name('accounting.chart-of-accounts');
    Route::get('/accounting/accounts-payable', AccountsPayable::class)->name('accounting.accounts-payable');
    Route::get('/accounting/cash-disbursements', CashDisbursements::class)->name('accounting.cash-disbursements');
    Route::get('/accounting/journal-entry', JournalEntry::class)->name('accounting.journal-entry');
    Route::get('/accounting/financial-statements', FinancialStatements::class)->name('accounting.financial-statements');
    Route::get('/accounting/general-ledger', GeneralLedger::class)->name('accounting.general-ledger');
    Route::get('/accounting/trial-balance', TrialBalance::class)->name('accounting.trial-balance');
    Route::get('/settings', SettingsPage::class)->name('settings');
    Route::get('/pos', PointOfSale::class)->name('pos');
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
    Route::get('/tax/vat-return-preparation', VatReturnPreparation::class)->name('tax.vat-return-preparation');
});
