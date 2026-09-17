<?php

use App\Http\Controllers\PasswordChangeController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CashSessionController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\OrderController;
use App\Http\Controllers\Web\PosCheckoutController;
use App\Http\Controllers\Web\PosController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\SettingController;
use App\Http\Controllers\Web\SupplierController;
use App\Http\Controllers\Web\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/pos', PosController::class)->middleware('permission:sales.create')->name('pos.index');
    Route::post('/pos/checkout', PosCheckoutController::class)->middleware('permission:sales.create')->name('pos.checkout');
    Route::post('/pos/shifts', [CashSessionController::class, 'store'])->middleware('permission:shifts.open')->name('pos.shifts.store');
    Route::post('/pos/shifts/{shift}/close', [CashSessionController::class, 'close'])->middleware('permission:shifts.close')->name('pos.shifts.close');
    Route::get('/products', [ProductController::class, 'index'])->middleware('permission:products.view')->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->middleware('permission:products.create')->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->middleware('permission:products.create')->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->middleware('permission:products.edit')->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('permission:products.edit')->name('products.update');
    Route::post('/products/{product}/fetch-image', [ProductController::class, 'fetchImage'])->middleware('permission:products.edit')->name('products.fetch-image');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete')->name('products.destroy');
    Route::get('/categories', [CategoryController::class, 'index'])->middleware('permission:categories.view')->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('permission:categories.create')->name('categories.store');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware('permission:categories.edit')->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:categories.delete')->name('categories.destroy');
    Route::get('/orders', [OrderController::class, 'index'])->middleware('permission:sales.view')->name('orders.index');
    Route::get('/reports', ReportController::class)->middleware('permission:reports.view_financial')->name('reports.index');
    Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:customers.view')->name('customers.index');
    Route::get('/suppliers', [SupplierController::class, 'index'])->middleware('permission:suppliers.view')->name('suppliers.index');
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->middleware('permission:users.create')->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.edit')->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.edit')->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('users.destroy');
    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
    Route::get('/roles/create', [RoleController::class, 'create'])->middleware('permission:roles.create')->name('roles.create');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('roles.store');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.edit')->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.edit')->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('roles.destroy');
    Route::get('/settings', [SettingController::class, 'index'])->middleware('permission:settings.view')->name('settings.index');
    Route::get('/password/change', [PasswordChangeController::class, 'edit'])->name('password.change.form');
    Route::post('/password/change', [PasswordChangeController::class, 'update'])->name('password.change.update');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});
