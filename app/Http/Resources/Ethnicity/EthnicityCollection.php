<?php

namespace App\Http\Resources\Ethnicity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class EthnicityCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return EthnicityResource::collection(
            $this->collection
        )->resolve();
    }
}
