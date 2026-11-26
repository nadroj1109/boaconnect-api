<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfficialPositionResource extends JsonResource
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
            'description' => $this->description,

            'is_active' => $this->is_active,
            'status' => $this->is_active ? 'Active' : 'Inactive',

            'created_at' => $this->created_at?->toISOString(),
            'created_at_label' => $this->created_at?->format('j M Y h:i a'),
            'updated_at' => $this->updated_at?->toISOString(),
            'updated_at_label' => $this->updated_at?->format('j M Y h:i a'),
        ];
    }
}
