<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseOrderDetailAuditTest extends TestCase
{
    use DatabaseTransactions;

    public function test_purchase_order_detail_writes_are_audited(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);
        Sanctum::actingAs($admin);
        $purchaseOrder = PurchaseOrder::factory()->create(['user_id' => $admin->id]);
        $product = Product::factory()->create();

        $createResponse = $this->postJson('/v1/purchase-order-details', [
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'cost_price' => 12.50,
        ])->assertCreated();

        $detail = PurchaseOrderDetail::query()->findOrFail($createResponse->json('data.id'));
        $createAudit = AuditLog::query()
            ->where('action', 'create')
            ->where('entity_type', PurchaseOrderDetail::class)
            ->where('entity_id', $detail->id)
            ->firstOrFail();

        $this->assertNull($createAudit->old_values);
        $this->assertSame('3.000', $createAudit->new_values['quantity']);

        $this->getJson("/v1/purchase-order-details/{$detail->id}")->assertOk();

        $viewAudit = AuditLog::query()
            ->where('action', 'view')
            ->where('entity_type', PurchaseOrderDetail::class)
            ->where('entity_id', $detail->id)
            ->firstOrFail();

        $this->assertNull($viewAudit->old_values);
        $this->assertSame($detail->id, $viewAudit->new_values['viewed_id']);

        $this->patchJson("/v1/purchase-order-details/{$detail->id}", [
            'quantity' => 5,
        ])->assertOk()->assertJsonPath('data.quantity', '5.000');

        $updateAudit = AuditLog::query()
            ->where('action', 'update')
            ->where('entity_type', PurchaseOrderDetail::class)
            ->where('entity_id', $detail->id)
            ->firstOrFail();

        $this->assertSame(['quantity' => '3.000'], $updateAudit->old_values);
        $this->assertSame(['quantity' => '5.000'], $updateAudit->new_values);

        $this->deleteJson("/v1/purchase-order-details/{$detail->id}")->assertNoContent();

        $this->assertTrue(AuditLog::query()
            ->where('action', 'delete')
            ->where('entity_type', PurchaseOrderDetail::class)
            ->where('entity_id', $detail->id)
            ->exists());
    }
}
