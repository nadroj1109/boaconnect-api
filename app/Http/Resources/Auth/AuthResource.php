<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Http\Resources\User\UserResource;
use App\Http\Resources\Resident\ResidentResource;
use App\Http\Resources\Barangay\BarangayResource;

class AuthResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [

            'token' => $this->token,

            'user' => UserResource::make(
                $this->user
            ),

            'resident' => ResidentResource::make(
                $this->resident
            ),

            'barangay' => BarangayResource::make(
                $this->barangay
            ),

        ];
    }
}