<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'register_id' => $this->register_id, 'opened_by_user_id' => $this->opened_by_user_id,
            'closed_by_user_id' => $this->closed_by_user_id, 'status' => $this->status, 'opened_at' => $this->opened_at,
            'closed_at' => $this->closed_at, 'opening_cash' => $this->opening_cash, 'closing_cash' => $this->closing_cash,
            'expected_cash' => $this->expected_cash, 'difference_amount' => $this->difference_amount, 'notes' => $this->notes,
        ];
    }
}
