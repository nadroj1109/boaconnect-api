<?php

namespace App\Http\Resources\Household;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HouseholdMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' =>
                $this->id,

            'full_name' =>
                $this->full_name,

            'relationship_to_hh_head_name' =>
                $this->relationship_to_hh_head_name,
        ];
    }
}
