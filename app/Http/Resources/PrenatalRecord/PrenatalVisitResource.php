<?php

namespace App\Http\Resources\PrenatalRecord;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrenatalVisitResource extends JsonResource
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
            'visit_number' => $this->visit_number,
            'visit_date' => $this->visit_date?->format('Y-m-d'),
            'aog_weeks' => $this->aog_weeks,
            'aog_days' => $this->aog_days,
            'blood_pressure' => $this->blood_pressure,
            'weight' => $this->weight,
            'fundal_height' => $this->fundal_height,
            'fetal_heart_beat' => $this->fetal_heart_beat,
            'presentation' => $this->presentation,
            'medicine_given' => $this->medicine_given,
            'danger_signs' => $this->danger_signs,
            'hbsag' => $this->hbsag,
            'hiv_screening' => $this->hiv_screening,
            'gestational_diabetes' => $this->gestational_diabetes,
            'cbc_hgb_hct' => $this->cbc_hgb_hct,
            'rpr_rdt' => $this->rpr_rdt,
            'remarks' => $this->remarks,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
