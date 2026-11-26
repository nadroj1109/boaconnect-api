<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /*
        |--------------------------------------------------------------------------
        | Determine Official Position
        |--------------------------------------------------------------------------
        |
        | New-person accounts use the temporary position stored on User.
        |
        | Existing barangay officials use the authoritative position
        | from the active BarangayOfficial record.
        |
        */

        $officialPosition =
            $this->officialPosition;

        $barangayOfficialId = null;

        if (
            !$officialPosition &&
            $this->resident
        ) {

            $official =
                $this->resident
                    ->barangayOfficials
                    ->first(
                        fn ($official) =>
                            $official->is_active
                    );

            $officialPosition = $official?->position;

            $barangayOfficialId = $official?->id;
        }

        return [

            'id' => $this->id,

            /*
            |--------------------------------------------------------------------------
            | Identity
            |--------------------------------------------------------------------------
            */

            'resident_id' => $this->resident_id,

            'display_name' => $this->display_name,

            'name' => $this->name,

            'first_name' => $this->first_name,

            'middle_name' => $this->middle_name,

            'last_name' => $this->last_name,

            'ext_name' => $this->ext_name,

            /*
            |--------------------------------------------------------------------------
            | Contact
            |--------------------------------------------------------------------------
            */

            'email' => $this->email,

            'mobile_number' => $this->mobile_number,

            /*
            |--------------------------------------------------------------------------
            | Account Status
            |--------------------------------------------------------------------------
            */

            'is_active' => $this->is_active,

            'onboarding_required' =>
                $this->onboarding_required,

            /*
            |--------------------------------------------------------------------------
            | Roles
            |--------------------------------------------------------------------------
            */

            'roles' => $this->roles
                ->pluck('name')
                ->values(),

            'role_ids' => $this->roles
                ->pluck('id')
                ->values(),

            'role_id' => $this->role_id,

            'role' =>
                optional(
                    $this->roles->first()
                )->name,

            /*
            |--------------------------------------------------------------------------
            | Resident
            |--------------------------------------------------------------------------
            */

            'resident' => $this->resident
                ? [
                    'id' =>
                        $this->resident->id,

                    'name' =>
                        $this->resident->first_name
                        . ' '
                        . $this->resident->last_name,
                ]
                : null,

            /*
            |--------------------------------------------------------------------------
            | Official Position
            |--------------------------------------------------------------------------
            */

            'barangay_official_id' => $barangayOfficialId,

            'official_position_id' =>
                $this->official_position_id
                ?? $officialPosition?->id,

            'official_position' =>
                $officialPosition
                    ? [
                        'id' =>
                            $officialPosition->id,

                        'name' =>
                            $officialPosition->name,
                    ]
                    : null,

            /*
            |--------------------------------------------------------------------------
            | Barangay
            |--------------------------------------------------------------------------
            */

            'barangay_id' =>
                $this->barangay_id,

            'barangay_name' =>
                $this->barangay->barangay ?? "",

            /*
            |--------------------------------------------------------------------------
            | Timestamps
            |--------------------------------------------------------------------------
            */

            'created_at' =>
                optional($this->created_at)
                    ->format(
                        'Y-m-d H:i:s'
                    ),

            'updated_at' =>
                optional($this->updated_at)
                    ->format(
                        'Y-m-d H:i:s'
                    ),

        ];
    }
}