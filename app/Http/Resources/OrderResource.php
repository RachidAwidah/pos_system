<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'invoice_number' => $this->invoice_number, 'customer_id' => $this->customer_id,
            'user_id' => $this->user_id, 'shift_id' => $this->shift_id, 'warehouse_id' => $this->warehouse_id,
            'status' => $this->status, 'payment_status' => $this->payment_status, 'subtotal_amount' => $this->subtotal_amount,
            'discount_amount' => $this->discount_amount, 'tax_amount' => $this->tax_amount, 'final_amount' => $this->final_amount,
            'paid_amount' => $this->paid_amount, 'due_amount' => $this->due_amount, 'refunded_amount' => $this->refunded_amount,
            'notes' => $this->notes, 'order_date' => $this->order_date, 'items' => $this->whenLoaded('items'),
            'payments' => $this->whenLoaded('payments'), 'customer' => $this->whenLoaded('customer'),
            'user' => $this->whenLoaded('user'), 'warehouse' => $this->whenLoaded('warehouse'),
            'shift' => $this->whenLoaded('shift'),
            'returns' => SalesReturnResource::collection($this->whenLoaded('returns')),
            'items_count' => $this->whenCounted('items'),
        ];
    }
}
