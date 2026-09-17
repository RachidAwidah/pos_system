<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CashSessionController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerPaymentController;
use App\Http\Controllers\Api\ExchangeRateController;
use App\Http\Controllers\Api\GoodsReceiptController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\InventoryCountController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SalesReturnController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\StripeController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplierPaymentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['cors', 'throttle:api'])->prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
    Route::get('/settings/public', [SettingController::class, 'publicIndex'])->name('settings.public');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        Route::post('/password/change', [AuthController::class, 'changePassword'])->name('password.change');

        Route::middleware('must_change_password')->group(function (): void {
            Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit_logs.view')->name('audit-logs.index');
            Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->middleware('permission:audit_logs.view')->name('audit-logs.show');
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
            Route::get('/products/reference-data', [ProductController::class, 'referenceData'])->middleware('permission:products.create')->name('products.reference-data');
            Route::post('/products', [ProductController::class, 'store'])->middleware('permission:products.create')->name('products.store');
            Route::get('/products/{product}', [ProductController::class, 'show'])->middleware('permission:products.view')->name('products.show');
            Route::match(['put', 'patch'], '/products/{product}', [ProductController::class, 'update'])->middleware('permission:products.edit')->name('products.update');
            Route::post('/products/{product}/fetch-image', [ProductController::class, 'fetchImage'])->middleware('permission:products.edit')->name('products.fetch-image');
            Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete')->name('products.destroy');

            Route::get('/categories', [CategoryController::class, 'index'])->middleware('permission:categories.view')->name('categories.index');
            Route::post('/categories', [CategoryController::class, 'store'])->middleware('permission:categories.create')->name('categories.store');
            Route::get('/categories/{category}', [CategoryController::class, 'show'])->middleware('permission:categories.view')->name('categories.show');
            Route::match(['put', 'patch'], '/categories/{category}', [CategoryController::class, 'update'])->middleware('permission:categories.edit')->name('categories.update');
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:categories.delete')->name('categories.destroy');

            Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:customers.view')->name('customers.index');
            Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:customers.create')->name('customers.store');
            Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:customers.view')->name('customers.show');
            Route::get('/customers/{customer}/statement', [CustomerController::class, 'statement'])->middleware('permission:customers.view')->name('customers.statement');
            Route::match(['put', 'patch'], '/customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.edit')->name('customers.update');
            Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:customers.delete')->name('customers.destroy');

            Route::get('/suppliers', [SupplierController::class, 'index'])->middleware('permission:suppliers.view')->name('suppliers.index');
            Route::post('/suppliers', [SupplierController::class, 'store'])->middleware('permission:suppliers.create')->name('suppliers.store');
            Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->middleware('permission:suppliers.view')->name('suppliers.show');
            Route::get('/suppliers/{supplier}/products', [SupplierController::class, 'products'])->middleware('permission:suppliers.view')->name('suppliers.products.index');
            Route::match(['put', 'patch'], '/suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('permission:suppliers.edit')->name('suppliers.update');
            Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware('permission:suppliers.delete')->name('suppliers.destroy');

            Route::get('/stock-movements', [StockMovementController::class, 'index'])->middleware('permission:inventory.view')->name('stock-movements.index');

            Route::prefix('inventory')->name('inventory.')->group(function (): void {
                Route::get('/balances', [InventoryController::class, 'index'])->middleware('permission:inventory.view')->name('balances.index');
                Route::get('/low-stock', [InventoryController::class, 'lowStock'])->middleware('permission:inventory.view')->name('low-stock.index');
                Route::get('/movements', [InventoryController::class, 'movements'])->middleware('permission:inventory.view')->name('movements.index');
                Route::put('/balances/{inventoryBalance}/reorder-level', [InventoryController::class, 'updateReorderLevel'])->middleware('permission:inventory.adjust')->name('balances.reorder-level.update');
                Route::post('/adjustments', [InventoryController::class, 'adjust'])->middleware('permission:inventory.adjust')->name('adjustments.store');
                Route::post('/damages', [InventoryController::class, 'recordDamage'])->middleware('permission:inventory.adjust')->name('damages.store');
                Route::post('/transfers', [InventoryController::class, 'transfer'])->middleware('permission:inventory.adjust')->name('transfers.store');
            });

            Route::get('/warehouses', [WarehouseController::class, 'index'])->middleware('permission:products.view')->name('warehouses.index');
            Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show'])->middleware('permission:products.view')->name('warehouses.show');

            Route::get('/inventory-counts', [InventoryCountController::class, 'index'])->middleware('permission:inventory.view')->name('inventory-counts.index');
            Route::post('/inventory-counts', [InventoryCountController::class, 'store'])->middleware('permission:inventory.count')->name('inventory-counts.store');
            Route::get('/inventory-counts/{inventoryCount}', [InventoryCountController::class, 'show'])->middleware('permission:inventory.view')->name('inventory-counts.show');
            Route::put('/inventory-counts/{inventoryCount}/items', [InventoryCountController::class, 'updateItems'])->middleware('permission:inventory.count')->name('inventory-counts.items.update');
            Route::post('/inventory-counts/{inventoryCount}/review', [InventoryCountController::class, 'review'])->middleware('permission:inventory.count')->name('inventory-counts.review');
            Route::post('/inventory-counts/{inventoryCount}/apply', [InventoryCountController::class, 'apply'])->middleware('permission:inventory.count')->name('inventory-counts.apply');
            Route::post('/inventory-counts/{inventoryCount}/cancel', [InventoryCountController::class, 'cancel'])->middleware('permission:inventory.count')->name('inventory-counts.cancel');

            Route::get('/settings', [SettingController::class, 'index'])->middleware('permission:settings.view')->name('settings.index');
            Route::put('/settings', [SettingController::class, 'bulkUpdate'])->middleware('permission:settings.edit')->name('settings.bulk-update');
            Route::put('/settings/{setting}', [SettingController::class, 'update'])->middleware('permission:settings.edit')->name('settings.update');

            Route::get('/exchange-rate', [ExchangeRateController::class, 'index'])->middleware('permission:settings.view')->name('exchange-rate.index');
            Route::post('/exchange-rate/refresh', [ExchangeRateController::class, 'refresh'])->middleware('permission:settings.edit')->name('exchange-rate.refresh');
            Route::get('/exchange-rate/{currency}', [ExchangeRateController::class, 'show'])->middleware('permission:settings.view')->name('exchange-rate.show');

            Route::get('/shifts/last-closed', [CashSessionController::class, 'lastClosed'])->middleware('permission:shifts.open')->name('shifts.last-closed');
            Route::post('/shifts/open', [CashSessionController::class, 'open'])->middleware('permission:shifts.open')->name('shifts.open');
            Route::post('/shifts/{shift}/cash-in', [CashSessionController::class, 'cashIn'])->middleware('permission:shifts.open')->name('shifts.cash-in');
            Route::post('/shifts/{shift}/cash-out', [CashSessionController::class, 'cashOut'])->middleware('permission:shifts.open')->name('shifts.cash-out');
            Route::get('/shifts/{shift}/summary', [CashSessionController::class, 'summary'])->middleware('permission:shifts.close')->name('shifts.summary');
            Route::post('/shifts/{shift}/close', [CashSessionController::class, 'close'])->middleware('permission:shifts.close')->name('shifts.close');
            Route::post('/shifts/{shift}/force-close', [CashSessionController::class, 'forceClose'])->middleware('permission:shifts.close')->name('shifts.force-close');

            Route::get('/orders', [SaleController::class, 'index'])->middleware('permission:sales.view')->name('orders.index');
            Route::get('/pos/reference-data', [SaleController::class, 'referenceData'])->middleware('permission:sales.create')->name('pos.reference-data');
            Route::get('/pos/products', [SaleController::class, 'products'])->middleware('permission:sales.create')->name('pos.products');
            Route::post('/orders', [SaleController::class, 'store'])->middleware('permission:sales.create')->name('orders.store');
            Route::get('/orders/{order}', [SaleController::class, 'show'])->middleware('permission:sales.view')->name('orders.show');
            Route::post('/orders/{order}/returns', [SalesReturnController::class, 'store'])->middleware('permission:sales.return')->name('orders.returns.store');

            Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->middleware('permission:purchases.view')->name('purchase-orders.index');
            Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->middleware('permission:purchases.create')->name('purchase-orders.store');
            Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->middleware('permission:purchases.view')->name('purchase-orders.show');
            Route::put('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->middleware('permission:purchases.edit')->name('purchase-orders.update');
            Route::delete('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->middleware('permission:purchases.delete')->name('purchase-orders.destroy');
            Route::post('/purchase-orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])->middleware('permission:purchases.edit')->name('purchase-orders.send');
            Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->middleware('permission:purchases.edit')->name('purchase-orders.cancel');
            Route::post('/purchase-orders/{purchaseOrder}/receipts', [GoodsReceiptController::class, 'store'])->middleware('permission:purchases.edit')->name('purchase-orders.receipts.store');
            Route::get('/goods-receipts', [GoodsReceiptController::class, 'index'])->middleware('permission:purchases.view')->name('goods-receipts.index');
            Route::get('/goods-receipts/{goodsReceipt}', [GoodsReceiptController::class, 'show'])->middleware('permission:purchases.view')->name('goods-receipts.show');

            Route::post('/customers/{customer}/payments', [CustomerPaymentController::class, 'store'])->middleware('permission:customers.edit')->name('customers.payments.store');
            Route::get('/suppliers/{supplier}/ledger', [SupplierController::class, 'ledger'])->middleware('permission:suppliers.view')->name('suppliers.ledger.index');
            Route::get('/suppliers/{supplier}/payments', [SupplierController::class, 'payments'])->middleware('permission:suppliers.view')->name('suppliers.payments.index');
            Route::post('/suppliers/{supplier}/payments', [SupplierPaymentController::class, 'store'])->middleware('permission:suppliers.edit')->name('suppliers.payments.store');

            Route::post('/payment/create-intent', [StripeController::class, 'createPaymentIntent'])->middleware('permission:sales.create')->name('payment.create-intent');

            Route::prefix('reports')->name('reports.')->group(function (): void {
                Route::get('/overview', [ReportController::class, 'overview'])->middleware('permission:reports.view_financial')->name('overview');
                Route::get('/sales', [ReportController::class, 'sales'])->middleware('permission:reports.view_sales')->name('sales');
                Route::get('/products', [ReportController::class, 'products'])->middleware('permission:reports.view_financial')->name('products');
                Route::get('/customers', [ReportController::class, 'customers'])->middleware('permission:reports.view_financial')->name('customers');
                Route::get('/profit', [ReportController::class, 'profit'])->middleware('permission:reports.view_financial')->name('profit');
            });

        });
    });
});
