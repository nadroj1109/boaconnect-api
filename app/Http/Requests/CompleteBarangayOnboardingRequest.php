<?php

namespace App\Http\Requests;

use App\Enums\CivilStatus;
use App\Enums\HighestEducationalAttainment;
use App\Enums\HouseholdType;
use App\Enums\HouseholdUnit;
use App\Enums\Nationality;
use App\Enums\Religion;
use App\Enums\Sex;
use App\Enums\TenureStatus;
use App\Enums\TypeResident;
use App\Models\SitioPurok;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteBarangayOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->onboarding_required === true
            && $user->barangay_id !== null
            && $user->official_position_id !== null
            && $user->resident_id === null;
    }

    public function rules(): array
    {
        $user = $this->user();
        $barangayId = $user->barangay_id;

        $hasSitioPuroks = SitioPurok::where(
            'barangay_id',
            $barangayId
        )->exists();

        return [
            // Household
            'household_no' => [
                'required',
                'max:50',
                Rule::unique('households', 'household_no'),
            ],

            'household_type' => [
                'required',
                Rule::enum(HouseholdType::class),
            ],
            'household_type_other' => [
                'nullable',
                'string',
                'max:100',
                'required_if:household_type,others',
            ],

            'tenure_status' => [
                'required',
                Rule::enum(TenureStatus::class),
            ],
            'tenure_status_other' => [
                'nullable',
                'string',
                'max:50',
                'required_if:tenure_status,others',
            ],

            'household_unit' => [
                'required',
                Rule::enum(HouseholdUnit::class),
            ],
            'household_unit_other' => [
                'nullable',
                'string',
                'max:50',
                'required_if:household_unit,others',
            ],

            'sitio_purok_id' => [
                Rule::requiredIf($hasSitioPuroks),
                'nullable',
                'integer',
                Rule::exists('sitio_puroks', 'id')
                    ->where('barangay_id', $user?->barangay_id)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],

            'sitio_purok' => [
                Rule::requiredIf(! $hasSitioPuroks),
                'nullable',
                'string',
                'max:255',
            ],

            'house_no' => ['nullable', 'string', 'max:100'],
            'street_name' => ['nullable', 'string', 'max:150'],
            'subdivision' => ['nullable', 'string', 'max:150'],

            // Personal information
            'first_name' => ['required', 'string', 'max:150'],
            'middle_name' => ['nullable', 'string', 'max:150'],
            'last_name' => ['required', 'string', 'max:150'],
            'ext_name' => ['nullable', 'string', 'max:50'],

            'date_of_birth' => [
                'required',
                'date',
                'before:today',
            ],
            'place_of_birth' => [
                'required',
                'string',
                'max:150',
            ],
            'sex' => [
                'required',
                Rule::enum(Sex::class),
            ],
            'gender' => ['nullable', 'string', 'max:50'],
            'gender_other' => ['nullable', 'string', 'max:50'],
            'civil_status' => [
                'required',
                Rule::enum(CivilStatus::class),
            ],

            // Resident information
            'type_of_resident' => [
                'required',
                Rule::enum(TypeResident::class),
            ],
            'highest_educ_attainment' => [
                'required',
                Rule::enum(HighestEducationalAttainment::class),
            ],
            'nationality' => [
                'required',
                Rule::enum(Nationality::class),
            ],
            'religion' => [
                'required',
                Rule::enum(Religion::class),
            ],
            'religion_other' => [
                'nullable',
                'string',
                'max:100',
                'required_if:religion,others',
            ],

            'philsys_card_no' => [
                'nullable',
                'string',
                'max:50',
            ],
            'res_mother_upon_birth' => [
                'nullable',
                'string',
                'max:150',
            ],
            'profession_occupation' => [
                'nullable',
                'string',
                'max:100',
            ],
            'email_address' => [
                'nullable',
                'email',
                'max:150',
                Rule::unique('residents', 'email_address'),
            ],
            'mobile_no' => ['nullable', 'string', 'max:20'],
            'tel_no' => ['nullable', 'string', 'max:20'],

            'blood_type' => ['nullable', 'string', 'max:10'],
            'height' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:999.99'],

            'registered_voter' => ['required', 'boolean'],
            'resident_voter' => ['required', 'boolean'],
            'last_voted_yr' => [
                'required',
                'integer',
                'min:0',
                'max:' . now()->year,
            ],

            'ethnicity_id' => [
                'nullable',
                'integer',
                Rule::exists('ethnicities', 'id'),
            ],

            'mother_fname' => ['nullable', 'string', 'max:150'],
            'mother_midname' => ['nullable', 'string', 'max:150'],
            'mother_lname' => ['nullable', 'string', 'max:150'],

            'govt_asst_progam' => ['nullable', 'string', 'max:50'],
            'govt_asst_progam_other' => [
                'nullable',
                'string',
                'max:50',
            ],

            'relationship_to_hh_head' => [
                'nullable',
                'string',
                'max:100',
            ],
            'source_of_income' => [
                'nullable',
                'string',
                'max:100',
            ],
            'source_of_income_other' => [
                'nullable',
                'string',
                'max:150',
            ],

            'monthly_income' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],
            'household_monthly_income' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],
        ];
    }
}