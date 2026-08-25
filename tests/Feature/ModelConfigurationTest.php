<?php

namespace Tests\Feature;

use App\Models\AiImporter;
use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\CustomerPayment;
use App\Models\FilterCondition;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryBalance;
use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\PersonalAccessToken;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Register;
use App\Models\Role;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\SavedFilter;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierPayment;
use App\Models\SupplierProduct;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class ModelConfigurationTest extends TestCase
{
    public function test_models_use_expected_table_and_uuid_configuration(): void
    {
        /** @var array<class-string<Model>, array{table: string, timestamps: bool}> $configurations */
        $configurations = [
            AiImporter::class => ['table' => 'ai_importers', 'timestamps' => false],
            AuditLog::class => ['table' => 'audit_logs', 'timestamps' => false],
            Category::class => ['table' => 'categories', 'timestamps' => false],
            CashMovement::class => ['table' => 'cash_movements', 'timestamps' => true],
            Customer::class => ['table' => 'customers', 'timestamps' => true],
            CustomerLedgerEntry::class => ['table' => 'customer_ledger_entries', 'timestamps' => true],
            CustomerPayment::class => ['table' => 'customer_payments', 'timestamps' => true],
            GoodsReceipt::class => ['table' => 'goods_receipts', 'timestamps' => true],
            GoodsReceiptItem::class => ['table' => 'goods_receipt_items', 'timestamps' => true],
            FilterCondition::class => ['table' => 'filter_conditions', 'timestamps' => false],
            InventoryBalance::class => ['table' => 'inventory_balances', 'timestamps' => true],
            InventoryCount::class => ['table' => 'inventory_counts', 'timestamps' => true],
            InventoryCountItem::class => ['table' => 'inventory_count_items', 'timestamps' => true],
            LoyaltyTransaction::class => ['table' => 'loyalty_transactions', 'timestamps' => true],
            Order::class => ['table' => 'orders', 'timestamps' => true],
            OrderItem::class => ['table' => 'order_items', 'timestamps' => true],
            Payment::class => ['table' => 'payments', 'timestamps' => true],
            PaymentMethod::class => ['table' => 'payment_methods', 'timestamps' => true],
            Permission::class => ['table' => 'permissions', 'timestamps' => true],
            PersonalAccessToken::class => ['table' => 'personal_access_tokens', 'timestamps' => true],
            Product::class => ['table' => 'products', 'timestamps' => true],
            PurchaseOrder::class => ['table' => 'purchase_orders', 'timestamps' => true],
            PurchaseOrderItem::class => ['table' => 'purchase_order_items', 'timestamps' => true],
            Register::class => ['table' => 'registers', 'timestamps' => true],
            Role::class => ['table' => 'roles', 'timestamps' => true],
            SavedFilter::class => ['table' => 'saved_filters', 'timestamps' => false],
            SalesReturn::class => ['table' => 'sales_returns', 'timestamps' => true],
            SalesReturnItem::class => ['table' => 'sales_return_items', 'timestamps' => true],
            Setting::class => ['table' => 'settings', 'timestamps' => true],
            Shift::class => ['table' => 'shifts', 'timestamps' => true],
            StockMovement::class => ['table' => 'stock_movements', 'timestamps' => true],
            Supplier::class => ['table' => 'suppliers', 'timestamps' => true],
            SupplierLedgerEntry::class => ['table' => 'supplier_ledger_entries', 'timestamps' => true],
            SupplierPayment::class => ['table' => 'supplier_payments', 'timestamps' => true],
            SupplierProduct::class => ['table' => 'supplier_products', 'timestamps' => true],
            Tax::class => ['table' => 'taxes', 'timestamps' => false],
            Unit::class => ['table' => 'units', 'timestamps' => false],
            User::class => ['table' => 'users', 'timestamps' => true],
            Warehouse::class => ['table' => 'warehouses', 'timestamps' => true],
        ];

        foreach ($configurations as $modelClass => $configuration) {
            $model = new $modelClass;

            $this->assertSame($configuration['table'], $model->getTable(), $modelClass.' table mismatch.');
            $this->assertSame('id', $model->getKeyName(), $modelClass.' primary key mismatch.');
            $this->assertSame('string', $model->getKeyType(), $modelClass.' key type mismatch.');
            $this->assertFalse($model->getIncrementing(), $modelClass.' must not use an incrementing key.');
            $this->assertSame($configuration['timestamps'], $model->usesTimestamps(), $modelClass.' timestamps mismatch.');
        }
    }
}
