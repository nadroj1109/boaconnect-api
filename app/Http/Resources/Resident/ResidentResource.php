<?php

namespace App\Http\Resources\Resident;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Resident\ResidentSectorResource;

class ResidentResource extends JsonResource
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
            'household_id' => $this->household_id ?? null,
            'is_household_head' => $this->is_household_head, 
            'type_of_resident' => $this->type_of_resident, 
            'type_of_resident_name' => $this->type_of_resident?->label(), 
            'philsys_card_no' => $this->philsys_card_no, 
            'last_name' => $this->last_name, 
            'first_name' => $this->first_name, 
            'middle_name' => $this->middle_name, 
            'ext_name' => $this->ext_name, 
            'full_name' => $this->full_name,
            'date_of_birth' => $this->date_of_birth?->format('F j, Y'),
            'date_of_birth_value' => $this->date_of_birth?->format('Y-m-d'),
            'age' => $this->date_of_birth ? $this->date_of_birth->age : null,
            'place_of_birth' => $this->place_of_birth, 
            'res_mother_upon_birth' => $this->res_mother_upon_birth, 
            'sex' => $this->sex, 
            'gender' => $this->gender, 
            'gender_name' => $this->gender?->label(), 
            'gender_other' => $this->gender_other, 
            'civil_status' => $this->civil_status?->value, 
            'civil_status_name' => $this->civil_status?->label(),
            'pregnant_woman' => $this->pregnant_woman, 
            'highest_educ_attainment' => $this->highest_educ_attainment, 
            'highest_educ_attainment_name' => $this->highest_educ_attainment?->label(), 
            'profession_occupation' => $this->profession_occupation, 
            'email_address' => $this->email_address, 
            'mobile_no' => $this->mobile_no, 
            'tel_no' => $this->tel_no, 
            'region_id' => $this->region_id, 
            'region_name' => $this->region->region ?? "", 
            'province_id' => $this->province_id, 
            'province_name' => $this->province->province ?? "", 
            'city_id' => $this->city_id, 
            'city_name' => $this->city->city ?? "", 
            'barangay_id' => $this->barangay_id, 
            'barangay_name' => $this->barangay->barangay ?? "", 
            'sitio_purok_id' => $this->sitio_purok_id,
            'sitio_purok' => $this->sitio_purok,
            'house_no' => $this->house_no, 
            'street_name' => $this->street_name, 
            'subdivision' => $this->subdivision, 
            'zip_code' => $this->zip_code, 
            'blood_type' => $this->blood_type, 
            'height' => $this->height, 
            'weight' => $this->weight, 
            'complexion' => $this->complexion, 
            'complexion_name' => $this->complexion?->label(), 
            'nationality' => $this->nationality, 
            'nationality_name' => $this->nationality?->label(), 
            'registered_voter' => $this->registered_voter, 
            'resident_voter' => $this->resident_voter, 
            'last_voted_yr' => $this->last_voted_yr, 
            'ethnicity_id' => $this->ethnicity_id, 
            'religion' => $this->religion, 
            'religion_name' => $this->religion?->label(), 
            'religion_other' => $this->religion_other,
            'mother_fname' => $this->mother_fname, 
            'mother_midname' => $this->mother_midname, 
            'mother_lname' => $this->mother_lname, 
            'govt_asst_progam' => $this->govt_asst_progam, 
            'govt_asst_progam_name' => $this->govt_asst_progam?->label(), 
            'govt_asst_progam_other' => $this->govt_asst_progam_other, 
            'relationship_to_hh_head' => $this->relationship_to_hh_head, 
            'relationship_to_hh_head_name' => $this->relationship_to_hh_head?->label(), 
            'source_of_income' => $this->source_of_income, 
            'source_of_income_name' => $this->source_of_income?->label(), 
            'source_of_income_other' => $this->source_of_income_other, 
            'monthly_income' => $this->monthly_income,
            'sectors' => ResidentSectorResource::collection(
                $this->whenLoaded('residentSectors')
            ),
            'ethnicity_name' => $this->ethnicity->ethnicity ?? "",
            'household' => $this->household ?? null,
            'household_registration' => $this->whenLoaded(
                'householdRegistration',
                fn () => $this->householdRegistration ? [
                    'id' => $this->householdRegistration->id,
                    'status' => $this->householdRegistration->status,
                    'rejection_reason' => $this->householdRegistration->rejection_reason,
                    'requested_household_id' => $this->householdRegistration->requested_household_id,
                    'household_details' => $this->householdRegistration->household_details,
                    'requested_household' => $this->householdRegistration->requestedHousehold ? [
                        'id' => $this->householdRegistration->requestedHousehold->id,
                        'household_no' => $this->householdRegistration->requestedHousehold->household_no,
                        'household_name' => $this->householdRegistration->requestedHousehold->household_name,
                        'complete_address' => $this->householdRegistration->requestedHousehold->complete_address,
                    ] : null,
                    'reviewed_by' => $this->householdRegistration->reviewed_by,
                    'reviewed_at' => $this->householdRegistration->reviewed_at?->toISOString(),
                ] : null
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
