<?php

namespace App\Http\Resources\Barangay;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BarangayResource extends JsonResource
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

            'city_id' => $this->city_id,

            'city' => optional($this->city)->city,

            'barangay' => $this->barangay,

            'is_active' => $this->is_active,

            'status' => $this->is_active ? 'Active' : 'Inactive',

        ];
    }
}
