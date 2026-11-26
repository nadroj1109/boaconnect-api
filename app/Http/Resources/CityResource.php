<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CityResource extends JsonResource
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
            'value' => $this->id,
            'name' => $this->city,
            'code'=>$this->psgc_code,
            'zip_code' => $this->zip_code,

        ];
    }
}
