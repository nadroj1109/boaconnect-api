<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\BarangayOfficial;
use App\Models\Household;
use App\Models\OfficialPosition;
use App\Models\Resident;
use App\Models\SitioPurok;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BarangayOnboardingService
{
    public function complete(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            // Lock the account to prevent concurrent onboarding requests.
            $account = User::query()
                ->lockForUpdate()
                ->findOrFail($user->id);

            // Recheck eligibility inside the transaction.
            if (
                ! $account->onboarding_required ||
                $account->resident_id !== null ||
                $account->barangay_id === null ||
                $account->official_position_id === null
            ) {
                throw ValidationException::withMessages([
                    'onboarding' => [
                        'This account is not eligible for this onboarding process.',
                    ],
                ]);
            }

            if (! $account->is_active || ! $account->activated_at) {
                throw ValidationException::withMessages([
                    'onboarding' => [
                        'Please activate your account before completing onboarding.',
                    ],
                ]);
            }

            // The assigned position must still be active.
            $position = OfficialPosition::query()
                ->whereKey($account->official_position_id)
                ->where('is_active', true)
                ->first();

            if (! $position) {
                throw ValidationException::withMessages([
                    'official_position' => [
                        'The assigned official position is no longer active. Contact an administrator.',
                    ],
                ]);
            }

            $barangay = Barangay::query()
                ->with('city')
                ->findOrFail($account->barangay_id);

            $city = $barangay->city;

            if (! $city) {
                throw ValidationException::withMessages([
                    'barangay' => [
                        'The assigned barangay does not have a linked city or municipality. Contact an administrator.',
                    ],
                ]);
            }

            $zipCode = $city->zip_code;

            /*
             * Resolve the Sitio/Purok.
             *
             * If the barangay already has active Sitio/Purok records,
             * use the selected existing record.
             *
             * Otherwise, create a new Sitio/Purok record from the
             * manually entered value.
             */
            $hasSitioPuroks = SitioPurok::query()
                ->where('barangay_id', $account->barangay_id)
                ->where('is_active', true)
                ->exists();

            if ($hasSitioPuroks) {
                // Existing Sitio/Purok options are available.
                $sitioPurok = SitioPurok::query()
                    ->whereKey($data['sitio_purok_id'] ?? null)
                    ->where('barangay_id', $account->barangay_id)
                    ->where('is_active', true)
                    ->first();

                if (! $sitioPurok) {
                    throw ValidationException::withMessages([
                        'sitio_purok_id' => [
                            'Select an active sitio/purok belonging to your assigned barangay.',
                        ],
                    ]);
                }
            } else {
                // No existing active Sitio/Purok options.
                $sitioPurokName = trim($data['sitio_purok'] ?? '');

                if ($sitioPurokName === '') {
                    throw ValidationException::withMessages([
                        'sitio_purok' => [
                            'Please enter your sitio/purok.',
                        ],
                    ]);
                }

                // Create the first Sitio/Purok record for this barangay.
                $sitioPurok = SitioPurok::create([
                    'barangay_id' => $account->barangay_id,
                    'sitio_purok' => $sitioPurokName,
                    'is_active' => true,
                ]);
            }

            $dateOfBirth = Carbon::parse($data['date_of_birth']);
            $fullName = trim(implode(' ', array_filter([
                $data['first_name'],
                $data['middle_name'] ?? null,
                $data['last_name'],
                $data['ext_name'] ?? null,
            ])));

            // The account email is the default resident email.
            $residentEmail = $data['email_address'] ?? $account->email;

            // Protect the resident table's unique email constraint,
            // including when the account email is used as the fallback.
            if (
                $residentEmail &&
                Resident::query()
                    ->where('email_address', $residentEmail)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'email_address' => [
                        'This email address is already associated with a resident.',
                    ],
                ]);
            }

            /*
             * 1. Create the household.
             *
             * The barangay comes from the provisioned account, never
             * from client-submitted data.
             */
            $household = Household::create([
                'barangay_id' => $account->barangay_id,

                'region_id' => $city->region_id,
                'province_id' => $city->province_id,
                'city_id' => $city->id,
                'zip_code' => $zipCode,

                'sitio_purok_id' => $sitioPurok->id,
                'sitio_purok' => $sitioPurok->sitio_purok,

                'house_no' => $data['house_no'] ?? null,
                'street_name' => $data['street_name'] ?? null,
                'subdivision' => $data['subdivision'] ?? null,

                'household_no' => $data['household_no'],
                'household_type' => $data['household_type'],
                'household_type_other' =>
                    $data['household_type_other'] ?? null,

                'tenure_status' => $data['tenure_status'],
                'tenure_status_other' =>
                    $data['tenure_status_other'] ?? null,

                'household_unit' => $data['household_unit'],
                'household_unit_other' =>
                    $data['household_unit_other'] ?? null,

                'household_name' => $fullName,

                // Initial counts for the person being registered.
                // Update these if additional household members are recorded.
                'no_of_family' => 1,
                'no_of_hh_member' => 1,
                'no_of_migrant' =>
                    $data['type_of_resident'] === 'migrant' ? 1 : 0,

                'monthly_income' =>
                    $data['household_monthly_income'] ?? 0,
            ]);

            /*
             * 2. Create the resident profile.
             */
            $resident = Resident::create([
                'household_id' => $household->id,
                'is_household_head' => true,

                'type_of_resident' => $data['type_of_resident'],
                'philsys_card_no' => $data['philsys_card_no'] ?? null,

                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'ext_name' => $data['ext_name'] ?? null,

                'date_of_birth' => $dateOfBirth->toDateString(),
                'age' => 0,
                'place_of_birth' => $data['place_of_birth'],
                'res_mother_upon_birth' =>
                    $data['res_mother_upon_birth'] ?? null,

                'sex' => $data['sex'],
                'gender' => $data['gender'] ?? null,
                'gender_other' => $data['gender_other'] ?? null,
                'civil_status' => $data['civil_status'],
                'pregnant_woman' => false,

                'highest_educ_attainment' =>
                    $data['highest_educ_attainment'],
                'profession_occupation' =>
                    $data['profession_occupation'] ?? null,

                'email_address' => $residentEmail,
                'mobile_no' =>
                    $data['mobile_no'] ?? $account->mobile_number,
                'tel_no' => $data['tel_no'] ?? null,

                'region_id' => $city->region_id,
                'province_id' => $city->province_id,
                'city_id' => $city->id,
                'barangay_id' => $barangay->id,

                'sitio_purok_id' => $sitioPurok->id,
                'sitio_purok' => $sitioPurok->sitio_purok,
                'house_no' => $data['house_no'] ?? null,
                'street_name' => $data['street_name'] ?? null,
                'subdivision' => $data['subdivision'] ?? null,
                'zip_code' => $zipCode,

                'blood_type' => $data['blood_type'] ?? null,
                'height' => $data['height'] ?? 0,
                'weight' => $data['weight'] ?? 0,

                'nationality' => $data['nationality'],
                'registered_voter' => $data['registered_voter'],
                'resident_voter' => $data['resident_voter'],
                'last_voted_yr' => $data['last_voted_yr'],

                'ethnicity_id' => $data['ethnicity_id'] ?? null,
                'religion' => $data['religion'],
                'religion_other' => $data['religion_other'] ?? null,

                'mother_fname' => $data['mother_fname'] ?? null,
                'mother_midname' => $data['mother_midname'] ?? null,
                'mother_lname' => $data['mother_lname'] ?? null,

                'govt_asst_progam' =>
                    $data['govt_asst_progam'] ?? null,
                'govt_asst_progam_other' =>
                    $data['govt_asst_progam_other'] ?? null,

                'relationship_to_hh_head' => null,

                'source_of_income' =>
                    $data['source_of_income'] ?? null,
                'source_of_income_other' =>
                    $data['source_of_income_other'] ?? null,

                'monthly_income' => $data['monthly_income'] ?? 0,
            ]);

            /*
             * 3. Create the official record.
             *
             * The position and barangay are taken from trusted
             * server-side records, not from the request payload.
             */
            BarangayOfficial::create([
                'barangay_id' => $account->barangay_id,
                'resident_id' => $resident->id,
                'official_position_id' => $position->id,
                'term_start' => now()->toDateString(),
                'term_end' => null,
                'is_active' => true,
            ]);

            /*
             * 4. Link the existing account and finish onboarding.
             *
             * Do not create another User or assign system roles here.
             */
            $account->update([
                'resident_id' => $resident->id,
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'ext_name' => $data['ext_name'] ?? null,
                'display_name' => $fullName,

                // This was only the temporary onboarding position.
                // The official record now owns the position assignment.
                'official_position_id' => null,
                'onboarding_required' => false,
            ]);

            return $account->fresh([
                'resident',
                'barangay',
                'roles',
            ]);
        }, 3);
    }
}