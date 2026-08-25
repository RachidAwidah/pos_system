<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\PurchaseOrderDetailController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
    Route::get('/settings/public', [SettingController::class, 'publicIndex'])->name('settings.public');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        Route::post('/password/change', [AuthController::class, 'changePassword'])->name('password.change');

        Route::middleware('must_change_password')->group(function (): void {
            Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
            Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
            Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:users.view')->name('users.show');
            Route::match(['put', 'patch'], '/users/{user}', [UserController::class, 'update'])->middleware('permission:users.edit')->name('users.update');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('users.destroy');

            Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
            Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('roles.store');
            Route::get('/roles/{role}', [RoleController::class, 'show'])->middleware('permission:roles.view')->name('roles.show');
            Route::match(['put', 'patch'], '/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.edit')->name('roles.update');
            Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('roles.destroy');

            Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:roles.view')->name('permissions.index');
            Route::get('/settings', [SettingController::class, 'index'])->middleware('permission:settings.view')->name('settings.index');
            Route::put('/settings/{setting}', [SettingController::class, 'update'])->middleware('permission:settings.edit')->name('settings.update');

            Route::apiResource('purchase-order-details', PurchaseOrderDetailController::class)
                ->middleware('permission:purchases.edit');
        });
    });
});
