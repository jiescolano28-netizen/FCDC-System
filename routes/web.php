<?php

use App\Http\Controllers\LoginController;
use App\Http\Controllers\RegisterController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InventoryController;

// HOMEPAGE
Route::get('/', function() {
    return view('homepage');
});

// LOGIN
Route::get('/login', [LoginController::class, 'showLogin'])
    ->name('login');

Route::post('/login', [LoginController::class, 'login'])
    ->name('login.submit');


// REGISTER
Route::get('/register', [RegisterController::class, 'showRegister'])
    ->name('register');

Route::post('/register', [RegisterController::class, 'register'])
    ->name('register.submit');


// LOGOUT
Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout');


// DASHBOARD
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware('auth')->name('dashboard');

// INVENTORY 
Route::get('/inventory', [InventoryController::class, 'index']);
Route::post('/inventory', [InventoryController::class, 'store']);
Route::put('/inventory/{inventory}', [InventoryController::class, 'update']);
Route::delete('/inventory/{inventory}', [InventoryController::class, 'destroy']);

