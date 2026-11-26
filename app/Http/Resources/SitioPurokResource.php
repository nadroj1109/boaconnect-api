<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SitioPurokResource extends JsonResource
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

            'barangay_id' => $this->barangay_id,

            'sitio_purok' => $this->sitio_purok,

            'is_active' => $this->is_active,

            'status' => $this->is_active ? 'Active' : 'Inactive',

        ];
    }
}
