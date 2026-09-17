<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryBalanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $availableQuantity = bcsub((string) $this->quantity_on_hand, (string) $this->quantity_reserved, 3);

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'quantity_on_hand' => $this->quantity_on_hand,
            'quantity_reserved' => $this->quantity_reserved,
            'quantity_available' => $availableQuantity,
            'reorder_level' => $this->reorder_level,
            'is_low_stock' => bccomp($availableQuantity, (string) $this->reorder_level, 3) <= 0,
            'average_cost' => $this->when(
                $request->user()?->hasPermission('reports.view_financial') ?? false,
                $this->average_cost,
            ),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'product_name' => $this->product->product_name,
                'sku' => $this->product->sku,
                'barcode' => $this->product->barcode,
                'unit' => $this->product->relationLoaded('unit') ? [
                    'id' => $this->product->unit->id,
                    'name' => $this->product->unit->name,
                    'symbol' => $this->product->unit->symbol,
                ] : null,
                'category' => $this->product->relationLoaded('category') ? [
                    'id' => $this->product->category->id,
                    'category_name' => $this->product->category->category_name,
                ] : null,
            ]),
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
                'code' => $this->warehouse->code,
                'is_active' => $this->warehouse->is_active,
            ]),
            'updated_at' => $this->updated_at,
        ];
    }
}
