<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Resident\ResidentResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
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
            'name' => $this->name,
            'email' => $this->email,

            'resident_id' => $this->resident_id,

            'display_name' => $this->display_name,

            'name' => $this->name,

            'first_name' => $this->first_name,

            'middle_name' => $this->middle_name,

            'last_name' => $this->last_name,

            'ext_name' => $this->ext_name,

            'mobile_number' => $this->mobile_number,

            'roles' => $this->getRoleNames(),
            'role' => optional($this->roles->first())->name,

            'permissions' => $this->getAllPermissions()
                ->pluck('name')
                ->values(),

            'barangay_id' => $this->barangay_id,
            'barangay_name' => $this->barangay->barangay ?? null,

            'is_active' => $this->is_active,
            'onboarding_required' => $this->onboarding_required,

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

            'created_at' => $this->created_at,

            'resident' => $this->whenLoaded(
                'resident',
                fn () => $this->resident
                    ? new ResidentResource($this->resident)
                    : null
            ),

        ];
    }
}
