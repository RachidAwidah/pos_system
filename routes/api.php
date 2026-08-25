<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CashSessionController;
use App\Http\Controllers\Api\CustomerPaymentController;
use App\Http\Controllers\Api\GoodsReceiptController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SalesReturnController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SupplierPaymentController;
use App\Http\Controllers\Api\UserController;
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
            Route::get('/products', [ProductController::class, 'index'])->middleware('permission:products.view')->name('products.index');
            Route::get('/settings', [SettingController::class, 'index'])->middleware('permission:settings.view')->name('settings.index');
            Route::put('/settings/{setting}', [SettingController::class, 'update'])->middleware('permission:settings.edit')->name('settings.update');

            Route::post('/shifts/open', [CashSessionController::class, 'open'])->middleware('permission:shifts.open')->name('shifts.open');
            Route::post('/shifts/{shift}/cash-in', [CashSessionController::class, 'cashIn'])->middleware('permission:shifts.open')->name('shifts.cash-in');
            Route::post('/shifts/{shift}/cash-out', [CashSessionController::class, 'cashOut'])->middleware('permission:shifts.open')->name('shifts.cash-out');
            Route::post('/shifts/{shift}/close', [CashSessionController::class, 'close'])->middleware('permission:shifts.close')->name('shifts.close');

            Route::get('/orders', [SaleController::class, 'index'])->middleware('permission:sales.view')->name('orders.index');
            Route::get('/pos/reference-data', [SaleController::class, 'referenceData'])->middleware('permission:sales.create')->name('pos.reference-data');
            Route::post('/orders', [SaleController::class, 'store'])->middleware('permission:sales.create')->name('orders.store');
            Route::get('/orders/{order}', [SaleController::class, 'show'])->middleware('permission:sales.view')->name('orders.show');
            Route::post('/orders/{order}/returns', [SalesReturnController::class, 'store'])->middleware('permission:sales.return')->name('orders.returns.store');

            Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->middleware('permission:purchases.view')->name('purchase-orders.index');
            Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->middleware('permission:purchases.create')->name('purchase-orders.store');
            Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->middleware('permission:purchases.view')->name('purchase-orders.show');
            Route::post('/purchase-orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])->middleware('permission:purchases.edit')->name('purchase-orders.send');
            Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->middleware('permission:purchases.edit')->name('purchase-orders.cancel');
            Route::post('/purchase-orders/{purchaseOrder}/receipts', [GoodsReceiptController::class, 'store'])->middleware('permission:purchases.edit')->name('purchase-orders.receipts.store');

            Route::post('/customers/{customer}/payments', [CustomerPaymentController::class, 'store'])->middleware('permission:customers.edit')->name('customers.payments.store');
            Route::post('/suppliers/{supplier}/payments', [SupplierPaymentController::class, 'store'])->middleware('permission:suppliers.edit')->name('suppliers.payments.store');

            Route::prefix('reports')->name('reports.')->group(function (): void {
                Route::get('/overview', [ReportController::class, 'overview'])->middleware('permission:reports.view_sales')->name('overview');
                Route::get('/sales', [ReportController::class, 'sales'])->middleware('permission:reports.view_sales')->name('sales');
                Route::get('/products', [ReportController::class, 'products'])->middleware('permission:reports.view_sales')->name('products');
                Route::get('/customers', [ReportController::class, 'customers'])->middleware('permission:reports.view_financial')->name('customers');
                Route::get('/profit', [ReportController::class, 'profit'])->middleware('permission:reports.view_financial')->name('profit');
            });

        });
    });
});
