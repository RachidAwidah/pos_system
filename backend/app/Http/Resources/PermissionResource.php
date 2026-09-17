<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = config('access_labels.permissions', [])[$this->permission_key] ?? [$this->permission_key, 'صلاحية مخصصة.'];

        return [
            'id' => $this->id,
            'key' => $this->permission_key,
            'name_ar' => $labels[0],
            'description_ar' => $labels[1],
        ];
    }
}
