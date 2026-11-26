<?php

namespace App\Http\Resources\PrenatalRecord;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrenatalRecordResource extends JsonResource
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
            'barangay_id' => $this->barangay_id,
            'registration_date' => $this->registration_date?->format('Y-m-d'),
            'spouse_name' => $this->spouse_name,
            'ob_score' => $this->ob_score,
            'lmp' => $this->lmp?->format('Y-m-d'),
            'edc' => $this->edc?->format('Y-m-d'),
            'height' => $this->height,
            'initial_weight' => $this->initial_weight,
            'bmi' => $this->bmi,
            'past_medical_history' => $this->past_medical_history,
            'family_history' => $this->family_history,
            'tt1_date' => $this->tt1_date?->format('Y-m-d'),
            'tt2_date' => $this->tt2_date?->format('Y-m-d'),
            'tt3_date' => $this->tt3_date?->format('Y-m-d'),
            'tt4_date' => $this->tt4_date?->format('Y-m-d'),
            'tt5_date' => $this->tt5_date?->format('Y-m-d'),
            'pregnancy_outcome' => $this->pregnancy_outcome,
            'remarks' => $this->remarks,

            'resident' => $this->whenLoaded('resident', function () {
                return [
                    'id' => $this->resident->id,
                    'full_name' => $this->resident->full_name,
                    'date_of_birth' => $this->resident->date_of_birth?->format('F j, Y'),
                    'age' => $this->resident->date_of_birth ? $this->resident->date_of_birth->age : null,
                    'civil_status' => $this->resident->civil_status,
                    'sitio_purok' => $this->resident->sitio_purok,
                    'mobile_no' => $this->resident->mobile_no,
                ];
            }),

            'barangay' => $this->whenLoaded('barangay', function () {
                return [
                    'id' => $this->barangay->id,
                    'name' => $this->barangay->barangay,
                ];
            }),

            'visits' => PrenatalVisitResource::collection(
                $this->whenLoaded('visits')
            ),

            'visits_count' => $this->whenCounted('visits'),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
