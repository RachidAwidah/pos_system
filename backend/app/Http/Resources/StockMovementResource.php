<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'user_id' => $this->user_id,
            'movement_type' => $this->movement_type->value,
            'quantity_delta' => $this->quantity_delta,
            'balance_before' => $this->balance_before,
            'balance_after' => $this->balance_after,
            'unit_cost' => $this->when(
                $request->user()?->hasPermission('reports.view_financial') ?? false,
                $this->unit_cost,
            ),
            'order_id' => $this->order_id,
            'purchase_order_id' => $this->purchase_order_id,
            'goods_receipt_id' => $this->goods_receipt_id,
            'inventory_count_id' => $this->inventory_count_id,
            'transfer_batch_id' => $this->transfer_batch_id,
            'notes' => $this->notes,
            'occurred_at' => $this->occurred_at,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'product_name' => $this->product->product_name,
                'sku' => $this->product->sku,
                'barcode' => $this->product->barcode,
            ]),
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
                'code' => $this->warehouse->code,
            ]),
            'user' => $this->whenLoaded('user', fn () => $this->user === null ? null : [
                'id' => $this->user->id,
                'full_name' => $this->user->full_name,
            ]),
        ];
    }
}
