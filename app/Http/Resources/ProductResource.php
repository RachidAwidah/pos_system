<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $balance = $this->relationLoaded('inventoryBalances') ? $this->inventoryBalances->first() : null;

        return [
            'id' => $this->id,
            'product_name' => $this->product_name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'type' => $this->type,
            'cost_price' => $this->cost_price,
            'price' => $this->price,
            'description' => $this->description,
            'image' => $this->image,
            'category' => $this->whenLoaded('category'),
            'unit' => $this->whenLoaded('unit'),
            'tax' => $this->whenLoaded('tax'),
            'inventory' => $balance ? [
                'warehouse_id' => $balance->warehouse_id,
                'quantity_on_hand' => $balance->quantity_on_hand,
                'quantity_reserved' => $balance->quantity_reserved,
                'quantity_available' => bcsub((string) $balance->quantity_on_hand, (string) $balance->quantity_reserved, 3),
                'reorder_level' => $balance->reorder_level,
                'average_cost' => $balance->average_cost,
            ] : null,
        ];
    }
}
