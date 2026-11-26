<?php

namespace App\Http\Resources\Ethnicity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EthnicityResource extends JsonResource
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

            'ethnicity' => $this->ethnicity,

            'is_active' => $this->is_active,

            'status' => $this->is_active ? 'Active' : 'Inactive',

        ];
    }
}
