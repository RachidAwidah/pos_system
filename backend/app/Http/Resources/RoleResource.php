<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = config('access_labels.roles', [])[$this->role_name] ?? [
            'name_ar' => $this->role_name,
            'description_ar' => 'دور مخصص تحدد صلاحياته من قائمة الصلاحيات الممنوحة له.',
        ];

        return [
            'id' => $this->id,
            'name' => $this->role_name,
            ...$labels,
            'is_system' => $this->is_system,
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
        ];
    }
}
