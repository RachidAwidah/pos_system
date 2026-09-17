<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryCountResource extends JsonResource
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
            'warehouse_id' => $this->warehouse_id,
            'started_by_user_id' => $this->started_by_user_id,
            'approved_by_user_id' => $this->approved_by_user_id,
            'status' => $this->status->value,
            'counted_at' => $this->counted_at,
            'applied_at' => $this->applied_at,
            'notes' => $this->notes,
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
                'code' => $this->warehouse->code,
            ]),
            'started_by' => $this->whenLoaded('startedBy', fn () => $this->startedBy === null ? null : [
                'id' => $this->startedBy->id,
                'full_name' => $this->startedBy->full_name,
            ]),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy === null ? null : [
                'id' => $this->approvedBy->id,
                'full_name' => $this->approvedBy->full_name,
            ]),
            'items_count' => $this->whenCounted('items'),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'expected_quantity' => $item->expected_quantity,
                'counted_quantity' => $item->counted_quantity,
                'difference_quantity' => $item->difference_quantity,
                'product' => $item->relationLoaded('product') ? [
                    'id' => $item->product->id,
                    'product_name' => $item->product->product_name,
                    'sku' => $item->product->sku,
                    'barcode' => $item->product->barcode,
                ] : null,
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
