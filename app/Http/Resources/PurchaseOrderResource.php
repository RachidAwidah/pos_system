<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'purchase_order_number' => $this->purchase_order_number, 'supplier_id' => $this->supplier_id,
            'warehouse_id' => $this->warehouse_id, 'user_id' => $this->user_id, 'status' => $this->status,
            'subtotal_amount' => $this->subtotal_amount, 'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount, 'total_amount' => $this->total_amount, 'ordered_at' => $this->ordered_at,
            'expected_at' => $this->expected_at, 'notes' => $this->notes, 'items' => $this->whenLoaded('items'),
            'goods_receipts' => $this->whenLoaded('goodsReceipts'),
        ];
    }
}
