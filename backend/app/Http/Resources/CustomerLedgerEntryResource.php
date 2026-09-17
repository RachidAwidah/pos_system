<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerLedgerEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $amount = (string) $this->amount_delta;

        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'entry_type' => $this->entry_type,
            'occurred_at' => $this->occurred_at,
            'debit' => bccomp($amount, '0', 2) > 0 ? $amount : '0.00',
            'credit' => bccomp($amount, '0', 2) < 0 ? bcsub('0', $amount, 2) : '0.00',
            'balance_before' => $this->balance_before,
            'balance_after' => $this->balance_after,
            'description' => $this->description,
            'order_id' => $this->order_id,
            'sales_return_id' => $this->sales_return_id,
            'customer_payment_id' => $this->customer_payment_id,
            'reference' => $this->whenLoaded('order', fn () => $this->order?->invoice_number),
        ];
    }
}
