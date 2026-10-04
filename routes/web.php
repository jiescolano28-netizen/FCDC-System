<?php

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LoginController;
use App\Livewire\HomePage;
use App\Livewire\Inventory\InventoryManagement;
use App\Livewire\Inventory\InventoryOverview;
use App\Livewire\Settings\SettingsPage;
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
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    Route::get('/settings', SettingsPage::class)->name('settings');

    Route::get('/inventory/manage', InventoryManagement::class)->name('inventory.management');
    Route::get('/inventory/overview', InventoryOverview::class)->name('inventory.overview');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::put('/inventory/{inventory}', [InventoryController::class, 'update'])->name('inventory.update');
    Route::delete('/inventory/{inventory}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
});
