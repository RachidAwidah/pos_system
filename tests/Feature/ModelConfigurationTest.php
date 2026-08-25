<?php

namespace Tests\Feature;

use App\Models\AiImporter;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Contact;
use App\Models\FilterCondition;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\PersonalAccessToken;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\Role;
use App\Models\SavedFilter;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
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
            Contact::class => ['table' => 'contacts', 'timestamps' => true],
            FilterCondition::class => ['table' => 'filter_conditions', 'timestamps' => false],
            Order::class => ['table' => 'orders', 'timestamps' => true],
            OrderDetail::class => ['table' => 'orders_details', 'timestamps' => false],
            Payment::class => ['table' => 'payments', 'timestamps' => true],
            Permission::class => ['table' => 'permissions', 'timestamps' => true],
            PersonalAccessToken::class => ['table' => 'personal_access_tokens', 'timestamps' => true],
            Product::class => ['table' => 'products', 'timestamps' => true],
            PurchaseOrder::class => ['table' => 'purchase_orders', 'timestamps' => true],
            PurchaseOrderDetail::class => ['table' => 'purchase_order_details', 'timestamps' => false],
            Role::class => ['table' => 'roles', 'timestamps' => true],
            SavedFilter::class => ['table' => 'saved_filters', 'timestamps' => false],
            Setting::class => ['table' => 'settings', 'timestamps' => true],
            Shift::class => ['table' => 'shifts', 'timestamps' => true],
            StockMovement::class => ['table' => 'stock_movements', 'timestamps' => true],
            Tax::class => ['table' => 'taxes', 'timestamps' => false],
            Unit::class => ['table' => 'units', 'timestamps' => false],
            User::class => ['table' => 'users', 'timestamps' => true],
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
