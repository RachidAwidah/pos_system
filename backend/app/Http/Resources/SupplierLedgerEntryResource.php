<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierLedgerEntryResource extends JsonResource
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
            'supplier_id' => $this->supplier_id,
            'entry_type' => $this->entry_type,
            'amount_delta' => $this->amount_delta,
            'balance_before' => $this->balance_before,
            'balance_after' => $this->balance_after,
            'description' => $this->description,
            'occurred_at' => $this->occurred_at,
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn () => $this->purchaseOrder === null ? null : [
                'id' => $this->purchaseOrder->id,
                'purchase_order_number' => $this->purchaseOrder->purchase_order_number,
            ]),
            'goods_receipt' => $this->whenLoaded('goodsReceipt', fn () => $this->goodsReceipt === null ? null : [
                'id' => $this->goodsReceipt->id,
                'receipt_number' => $this->goodsReceipt->receipt_number,
            ]),
            'supplier_payment_id' => $this->supplier_payment_id,
            'user' => $this->whenLoaded('user', fn () => $this->user === null ? null : [
                'id' => $this->user->id,
                'full_name' => $this->user->full_name,
            ]),
        ];
    }
}
