<?php

namespace App\Http\Resources\ChildNutrition;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChildNutritionMonitoringResource extends JsonResource
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

            'resident_id' => $this->resident_id,
            'monitoring_year' => $this->monitoring_year,
            'sequence_no' => $this->sequence_no,

            'caregiver_name' => $this->caregiver_name,
            'birthweight' => $this->birthweight,
            'low_birth_weight' => $this->low_birth_weight,

            'resident' => $this->whenLoaded('resident', function () {
                return [
                    'id' => $this->resident->id,
                    'full_name' => $this->resident->full_name,
                    'date_of_birth' => $this->resident->date_of_birth?->format('F j, Y'),
                    'age' => $this->resident->date_of_birth
                        ? $this->resident->date_of_birth->age
                        : null,
                    'sex' => $this->resident->sex,
                    'sitio_purok' => $this->resident->sitio_purok,
                    'mobile_no' => $this->resident->mobile_no,
                ];
            }),

            'barangay' => $this->whenLoaded('barangay', function () {
                return [
                    'id' => $this->barangay?->id,
                    'name' => $this->barangay?->name,
                ];
            }),

            'assessments' => ChildNutritionAssessmentResource::collection(
                $this->whenLoaded('assessments')
            ),

            'assessments_count' => $this->whenCounted('assessments'),

            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator?->id,
                    'name' => $this->creator?->name,
                ];
            }),

            'updater' => $this->whenLoaded('updater', function () {
                return [
                    'id' => $this->updater?->id,
                    'name' => $this->updater?->name,
                ];
            }),

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
