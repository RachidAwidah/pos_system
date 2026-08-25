<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceiptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'receipt_number' => $this->receipt_number, 'purchase_order_id' => $this->purchase_order_id,
            'warehouse_id' => $this->warehouse_id, 'received_by_user_id' => $this->received_by_user_id,
            'status' => $this->status, 'subtotal_amount' => $this->subtotal_amount, 'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount, 'total_amount' => $this->total_amount,
            'supplier_reference' => $this->supplier_reference, 'notes' => $this->notes, 'received_at' => $this->received_at,
            'items' => $this->whenLoaded('items'),
        ];
    }
}
