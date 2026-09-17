<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            'category_name' => $this->category_name,
            'parent_id' => $this->parent_id,
            'depth' => $this->when($this->resource->offsetExists('depth'), $this->depth),
            'path' => $this->when($this->resource->offsetExists('path'), $this->path),
            'parent' => new self($this->whenLoaded('parent')),
            'children' => self::collection($this->whenLoaded('children')),
            'children_count' => $this->whenCounted('children'),
            'products_count' => $this->whenCounted('products'),
            'tree_products_count' => $this->when($this->resource->offsetExists('tree_products_count'), $this->tree_products_count),
        ];
    }
}
