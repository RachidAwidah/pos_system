<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierProductResource extends JsonResource
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
            'product_id' => $this->product_id,
            'supplier_sku' => $this->supplier_sku,
            'last_cost' => $this->when($request->user()?->hasPermission('reports.view_financial') ?? false, $this->last_cost),
            'minimum_order_quantity' => $this->minimum_order_quantity,
            'lead_time_days' => $this->lead_time_days,
            'is_preferred' => $this->is_preferred,
            'product' => $this->whenLoaded('product', fn () => $this->product === null ? null : [
                'id' => $this->product->id,
                'product_name' => $this->product->product_name,
                'sku' => $this->product->sku,
            ]),
        ];
    }
}
