<?php

namespace App\Http\Resources\Barangay;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class BarangayCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return BarangayResource::collection(
            $this->collection
        )->resolve();
    }
}
