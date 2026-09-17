<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReturnResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'return_number' => $this->return_number, 'order_id' => $this->order_id,
            'user_id' => $this->user_id, 'shift_id' => $this->shift_id, 'warehouse_id' => $this->warehouse_id,
            'status' => $this->status, 'subtotal_amount' => $this->subtotal_amount, 'tax_amount' => $this->tax_amount,
            'refund_amount' => $this->refund_amount, 'reason' => $this->reason, 'returned_at' => $this->returned_at,
            'items' => $this->whenLoaded('items'), 'payments' => $this->whenLoaded('payments'),
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->id,
                'invoice_number' => $this->order->invoice_number,
                'status' => $this->order->status,
                'payment_status' => $this->order->payment_status,
            ]),
            'user' => $this->whenLoaded('user'),
            'warehouse' => $this->whenLoaded('warehouse'),
            'shift' => $this->whenLoaded('shift'),
        ];
    }
}
