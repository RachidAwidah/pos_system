<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
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
            'name' => $this->name,
            'company_name' => $this->company_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'tax_number' => $this->tax_number,
            'payable_limit' => $this->payable_limit,
            'balance' => $this->balance,
            'purchases_total' => $this->whenHas('purchases_total', fn ($total) => bcadd((string) ($total ?? '0'), '0', 2)),
            'created_at' => $this->created_at,
        ];
    }
}
