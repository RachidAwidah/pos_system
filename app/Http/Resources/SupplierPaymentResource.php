<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierPaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'supplier_id' => $this->supplier_id, 'purchase_order_id' => $this->purchase_order_id,
            'user_id' => $this->user_id, 'payment_method_id' => $this->payment_method_id, 'status' => $this->status,
            'amount' => $this->amount, 'reference_number' => $this->reference_number, 'notes' => $this->notes, 'paid_at' => $this->paid_at,
        ];
    }
}
