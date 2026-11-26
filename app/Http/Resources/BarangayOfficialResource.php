<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BarangayOfficialResource extends JsonResource
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
            'resident_id' => $this->resident_id,
            'official_position_id' =>
                $this->official_position_id,

            'resident_name' =>
                $this->resident?->full_name,

            'position_name' =>
                $this->position?->name,

            'barangay_name' =>
                $this->barangay?->barangay,

            'barangay' => $this->whenLoaded(
                'barangay',
                fn () => [
                    'id' => $this->barangay->id,
                    'name' => $this->barangay->barangay,
                ]
            ),

            'resident' => $this->whenLoaded(
                'resident',
                fn () => [
                    'id' => $this->resident->id,
                    'full_name' => $this->resident->full_name,
                ]
            ),

            'position' => $this->whenLoaded(
                'position',
                fn () => [
                    'id' => $this->position->id,
                    'name' => $this->position->name,
                ]
            ),

            'term_start' =>
                $this->term_start?->toDateString(),

            'term_start_label' =>
                $this->term_start?->format('j M Y'),

            'term_end' =>
                $this->term_end?->toDateString(),

            'term_end_label' =>
                $this->term_end?->format('j M Y'),

            'is_active' => $this->is_active,

            'status' =>
                $this->is_active
                    ? 'Active'
                    : 'Inactive',

            'created_at' =>
                $this->created_at?->toISOString(),

            'created_at_label' =>
                $this->created_at?->format('j M Y h:i a'),

            'updated_at' =>
                $this->updated_at?->toISOString(),

            'updated_at_label' =>
                $this->updated_at?->format('j M Y h:i a'),
        ];
    }
}
