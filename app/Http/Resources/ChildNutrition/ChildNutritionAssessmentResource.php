<?php

namespace App\Http\Resources\ChildNutrition;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChildNutritionAssessmentResource extends JsonResource
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
            'child_nutrition_monitoring_id' => $this->child_nutrition_monitoring_id,

            'assessment_month' => $this->assessment_month,
            'assessment_month_name' => $this->assessment_date?->format('F'),

            'assessment_date' => $this->assessment_date?->format('Y-m-d'),
            'age_months' => $this->age_months,

            'weight' => $this->weight,
            'height' => $this->height,
            'muac' => $this->muac,
            'edema' => $this->edema,

            'weight_for_age' => $this->weight_for_age,
            'height_for_age' => $this->height_for_age,
            'weight_for_height' => $this->weight_for_height,
            'muac_edema_status' => $this->muac_edema_status,

            'remarks' => $this->remarks,

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
