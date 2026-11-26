<?php

namespace App\Services\ResidentAuth;

use App\Models\User;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use App\Enums\HouseholdRelationship;
use App\Enums\ResidentRegistrationResult;
use App\Enums\VerificationChannel;
use App\Enums\VerificationPurpose;
use App\Models\Household;
use App\Models\Resident;
use App\Models\ResidentHouseholdRegistration;
use App\Models\SitioPurok;
use App\Models\VerificationCode;
use App\Services\ResidentService;

use App\Services\Auth\DTOs\AuthSession;
use App\Services\Auth\DTOs\ResidentRegistrationResponse;

class ResidentRegistrationService
{
    public function __construct(
        protected ResidentMatchingService $matching,
        protected ResidentService $residents,
    ) {}

    public function register(
        string $verificationToken,
        string $password,
    ): ResidentRegistrationResponse {

        /*
        |--------------------------------------------------------------------------
        | Match Resident
        |--------------------------------------------------------------------------
        */

        $match = $this->matching->match(
            $verificationToken
        );

        if ($match->failed()) {

            return new ResidentRegistrationResponse(

                false,

                $match->message

            );

        }

        return DB::transaction(function () use (
            $match,
            $password
        ) {

            $user = User::registerResident(

                resident: $match->resident,

                verification: $match->verification,

                password: $password,

            );

            $match->verification->consume();

            return new ResidentRegistrationResponse(

                true,

                ResidentRegistrationResult::SUCCESS->message(),

                session: new AuthSession(

                    token: $user->issueToken(),

                    user: $user,

                    resident: $match->resident,

                    barangay: $user->barangay,

                ),

            );

        });

    }

    public function registerNewResident(array $data): ResidentRegistrationResponse
    {
        return DB::transaction(function () use ($data) {

            /*
            |--------------------------------------------------------------------------
            | Validate Verification
            |--------------------------------------------------------------------------
            */

            $verification = VerificationCode::query()
                ->where('verification_token', $data['verification_token'])
                ->lockForUpdate()
                ->first();

            if (! $verification) {
                return new ResidentRegistrationResponse(
                    false,
                    'Invalid verification token.'
                );
            }

            if ($verification->purpose !== VerificationPurpose::REGISTER) {
                return new ResidentRegistrationResponse(
                    false,
                    'This verification token cannot be used for registration.'
                );
            }

            if ($verification->isConsumed()) {
                return new ResidentRegistrationResponse(
                    false,
                    'This verification token has already been used.'
                );
            }

            if ($verification->isExpired()) {
                return new ResidentRegistrationResponse(
                    false,
                    'Your verification token has expired. Please verify your contact again.'
                );
            }

            if (! $verification->isVerified()) {
                return new ResidentRegistrationResponse(
                    false,
                    'Your contact information has not been verified.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Prepare Household Registration Information
            |--------------------------------------------------------------------------
            */

            $relationshipToHead = $data['relationship_to_hh_head'];

            $isHouseholdHead =
                $relationshipToHead === HouseholdRelationship::HouseholdHead->value;

            $requestedHouseholdNo = $data['requested_household_no'] ?? null;
            $householdDetails = $data['household_details'] ?? null;

            $requestedHouseholdId = null;

            if (! $isHouseholdHead) {
                $requestedHouseholdId = Household::query()
                    ->where('barangay_id', $data['barangay_id'])
                    ->where('household_no', $requestedHouseholdNo)
                    ->lockForUpdate()
                    ->value('id');

                if (! $requestedHouseholdId) {
                    throw ValidationException::withMessages([
                        'requested_household_no' =>
                            'The household number was not found in the selected barangay.',
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Prepare Registration Information
            |--------------------------------------------------------------------------
            */

            $password = $data['password'];

            unset(
                $data['verification_token'],
                $data['password'],
                $data['password_confirmation'],
                $data['requested_household_no'],
                // $data['requested_household_id'],
                $data['household_details']
            );

            // Always use the verified contact information.
            if ($verification->channel === VerificationChannel::EMAIL) {
                $data['email_address'] = $verification->recipient;
            } else {
                $data['mobile_no'] = $verification->recipient;
            }

            /*
            |--------------------------------------------------------------------------
            | Resolve Sitio/Purok
            |--------------------------------------------------------------------------
            */

            $barangayId = $data['barangay_id'];

            $sitioPurokId = $data['sitio_purok_id'] ?? null;
            $sitioPurokName = trim($data['sitio_purok'] ?? '');

            unset(
                $data['sitio_purok_id'],
                $data['sitio_purok']
            );

            $hasExistingSitioPuroks = SitioPurok::query()
                ->where('barangay_id', $barangayId)
                ->where('is_active', true)
                ->exists();

            if ($hasExistingSitioPuroks) {

                // Existing Sitio/Purok must be selected.
                $sitioPurok = SitioPurok::query()
                    ->where('id', $sitioPurokId)
                    ->where('barangay_id', $barangayId)
                    ->where('is_active', true)
                    ->first();

                if (! $sitioPurok) {
                    throw ValidationException::withMessages([
                        'sitio_purok_id' => 'Please select a valid Sitio/Purok.',
                    ]);
                }
            } else {

                // No existing options: allow the resident to enter a new one.
                $sitioPurok = null;

                if ($sitioPurokName !== '') {

                    $sitioPurok = SitioPurok::withTrashed()
                        ->where('barangay_id', $barangayId)
                        ->whereRaw('LOWER(sitio_purok) = ?', [
                            mb_strtolower($sitioPurokName),
                        ])
                        ->first();

                    if ($sitioPurok && $sitioPurok->trashed()) {
                        $sitioPurok->restore();
                        $sitioPurok->is_active = true;
                        $sitioPurok->save();
                    }

                    if (! $sitioPurok) {
                        $sitioPurok = SitioPurok::create([
                            'barangay_id' => $barangayId,
                            'sitio_purok' => $sitioPurokName,
                            'is_active' => true,
                        ]);
                    }
                }
            }

            // Save both the ID and name to the resident.
            $data['sitio_purok_id'] = $sitioPurok?->id;
            $data['sitio_purok'] = $sitioPurok?->sitio_purok;

            /*
            |--------------------------------------------------------------------------
            | Validate Requested Household
            |--------------------------------------------------------------------------
            */

            if (! $isHouseholdHead) {

                $requestedHousehold = Household::query()
                    ->where('id', $requestedHouseholdId)
                    ->where('barangay_id', $barangayId)
                    ->first();

                if (! $requestedHousehold) {
                    throw ValidationException::withMessages([
                        'requested_household_no' =>
                            'The household number was not found in the selected barangay.',
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Create Unassigned Resident
            |--------------------------------------------------------------------------
            */

            $resident = $this->residents->storeUnassignedRegistration($data);

            /*
            |--------------------------------------------------------------------------
            | Create Pending Household Registration
            |--------------------------------------------------------------------------
            */

            if ($isHouseholdHead) {

                // Only keep the household fields that the resident is allowed to propose.
                $details = collect($householdDetails ?? [])
                    ->only([
                        'no_of_family',
                        'no_of_hh_member',
                        'no_of_migrant',
                        'household_type',
                        'household_type_other',
                        'tenure_status',
                        'tenure_status_other',
                        'household_unit',
                        'household_unit_other',
                        'household_name',
                        'monthly_income',
                    ])
                    ->all();

                // Address information comes from the registered resident,
                // not from separately submitted household address fields.
                $details = array_merge($details, [
                    'region_id' => $resident->region_id,
                    'province_id' => $resident->province_id,
                    'city_id' => $resident->city_id,
                    'barangay_id' => $resident->barangay_id,
                    'zip_code' => $resident->zip_code,
                    'house_no' => $resident->house_no,
                    'street_name' => $resident->street_name,
                    'subdivision' => $resident->subdivision,
                    'sitio_purok_id' => $resident->sitio_purok_id,
                    'sitio_purok' => $resident->sitio_purok,
                    'complete_address' => $resident->complete_address,
                ]);

                ResidentHouseholdRegistration::create([
                    'resident_id' => $resident->id,
                    'requested_household_id' => null,
                    'household_details' => $details,
                ]);

            } else {

                // The resident is requesting to join an existing household.
                // The actual household assignment happens after Secretary approval.
                ResidentHouseholdRegistration::create([
                    'resident_id' => $resident->id,
                    'requested_household_id' => $requestedHouseholdId,
                    'household_details' => null,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Create Resident Account
            |--------------------------------------------------------------------------
            */

            $user = User::registerResident(
                resident: $resident,
                verification: $verification,
                password: $password,
            );

            /*
            |--------------------------------------------------------------------------
            | Consume Verification
            |--------------------------------------------------------------------------
            */

            $verification->consume();

            /*
            |--------------------------------------------------------------------------
            | Return Authenticated Session
            |--------------------------------------------------------------------------
            */

            return new ResidentRegistrationResponse(
                true,
                ResidentRegistrationResult::SUCCESS->message(),
                session: new AuthSession(
                    token: $user->issueToken(),
                    user: $user,
                    resident: $resident,
                    barangay: $user->barangay,
                ),
            );
        });
    }

    public function resubmit(
        Resident $resident,
        User $user,
        array $data
    ): Resident {
        return DB::transaction(function () use ($resident, $user, $data) {

            /*
            |--------------------------------------------------------------------------
            | Lock and Verify Resident
            |--------------------------------------------------------------------------
            */

            $resident = Resident::query()
                ->whereKey($resident->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Ensure this resident belongs to the authenticated account.
            if (
                (int) $user->resident_id !== (int) $resident->id ||
                (int) $user->barangay_id !== (int) $resident->barangay_id
            ) {
                abort(403, 'You are not authorized to update this registration.');
            }

            // A resident cannot change barangays through resubmission.
            if (
                (int) $data['barangay_id'] !== (int) $resident->barangay_id
            ) {
                throw ValidationException::withMessages([
                    'barangay_id' => 'You cannot change your registered barangay through resubmission.',
                ]);
            }

            if ($resident->household_id !== null) {
                throw ValidationException::withMessages([
                    'registration' => 'Your resident account already has an assigned household.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Lock and Verify Existing Registration
            |--------------------------------------------------------------------------
            */

            $registration = ResidentHouseholdRegistration::query()
                ->where('resident_id', $resident->id)
                ->lockForUpdate()
                ->first();

            if (! $registration) {
                throw ValidationException::withMessages([
                    'registration' => 'Your household registration record could not be found.',
                ]);
            }

            if (
                $registration->status !==
                ResidentHouseholdRegistration::STATUS_REJECTED
            ) {
                throw ValidationException::withMessages([
                    'registration' => 'Only rejected registrations can be resubmitted.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Prepare Household Registration Information
            |--------------------------------------------------------------------------
            */

            $relationshipToHead = $data['relationship_to_hh_head'];

            $isHouseholdHead =
                $relationshipToHead === HouseholdRelationship::HouseholdHead->value;

            $requestedHouseholdNo = $data['requested_household_no'] ?? null;
            $householdDetails = $data['household_details'] ?? null;

            $requestedHouseholdId = null;

            if (! $isHouseholdHead) {
                $requestedHouseholdId = Household::query()
                    ->where('barangay_id', $resident->barangay_id)
                    ->where('household_no', $requestedHouseholdNo)
                    ->lockForUpdate()
                    ->value('id');

                if (! $requestedHouseholdId) {
                    throw ValidationException::withMessages([
                        'requested_household_no' =>
                            'The household number was not found in your barangay.',
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Prepare Resident Information
            |--------------------------------------------------------------------------
            */

            $sectors = $data['sectors'] ?? null;

            unset(
                $data['password'],
                $data['password_confirmation'],
                $data['verification_token'],
                $data['requested_household_no'],
                $data['household_details'],
                $data['sectors'],
                $data['household_id'],
                $data['is_household_head']
            );

            // Barangay ownership must remain unchanged.
            $data['barangay_id'] = $resident->barangay_id;

            /*
            |--------------------------------------------------------------------------
            | Resolve Sitio/Purok
            |--------------------------------------------------------------------------
            */

            $barangayId = $resident->barangay_id;

            $sitioPurokId = $data['sitio_purok_id'] ?? null;
            $sitioPurokName = trim($data['sitio_purok'] ?? '');

            unset(
                $data['sitio_purok_id'],
                $data['sitio_purok']
            );

            $hasExistingSitioPuroks = SitioPurok::query()
                ->where('barangay_id', $barangayId)
                ->where('is_active', true)
                ->exists();

            if ($hasExistingSitioPuroks) {

                $sitioPurok = SitioPurok::query()
                    ->where('id', $sitioPurokId)
                    ->where('barangay_id', $barangayId)
                    ->where('is_active', true)
                    ->first();

                if (! $sitioPurok) {
                    throw ValidationException::withMessages([
                        'sitio_purok_id' => 'Please select a valid Sitio/Purok.',
                    ]);
                }

            } else {

                $sitioPurok = null;

                if ($sitioPurokName !== '') {

                    $sitioPurok = SitioPurok::withTrashed()
                        ->where('barangay_id', $barangayId)
                        ->whereRaw('LOWER(sitio_purok) = ?', [
                            mb_strtolower($sitioPurokName),
                        ])
                        ->first();

                    if ($sitioPurok && $sitioPurok->trashed()) {
                        $sitioPurok->restore();
                        $sitioPurok->is_active = true;
                        $sitioPurok->save();
                    }

                    if (! $sitioPurok) {
                        $sitioPurok = SitioPurok::create([
                            'barangay_id' => $barangayId,
                            'sitio_purok' => $sitioPurokName,
                            'is_active' => true,
                        ]);
                    }
                }
            }

            $data['sitio_purok_id'] = $sitioPurok?->id;
            $data['sitio_purok'] = $sitioPurok?->sitio_purok;
            $data['email_address'] = $resident->email_address;

            /*
            |--------------------------------------------------------------------------
            | Update Existing Resident
            |--------------------------------------------------------------------------
            */

            $resident->update($data);

            // Synchronize the login mobile number.
            $user->mobile_number = $resident->mobile_no;
            $user->save();

            // Keep the resident unassigned until Secretary approval.
            $resident->household_id = null;
            $resident->is_household_head = false;
            $resident->save();

            if ($sectors !== null) {
                $this->residents->syncResidentSectors(
                    $resident,
                    $sectors
                );
            }

            $resident->refresh();

            /*
            |--------------------------------------------------------------------------
            | Rebuild Household Registration Details
            |--------------------------------------------------------------------------
            */

            if ($isHouseholdHead) {

                $details = collect($householdDetails ?? [])
                    ->only([
                        'no_of_family',
                        'no_of_hh_member',
                        'no_of_migrant',
                        'household_type',
                        'household_type_other',
                        'tenure_status',
                        'tenure_status_other',
                        'household_unit',
                        'household_unit_other',
                        'household_name',
                        'monthly_income',
                    ])
                    ->all();

                // Household address comes from the updated resident record.
                $details = array_merge($details, [
                    'region_id' => $resident->region_id,
                    'province_id' => $resident->province_id,
                    'city_id' => $resident->city_id,
                    'barangay_id' => $resident->barangay_id,
                    'zip_code' => $resident->zip_code,
                    'house_no' => $resident->house_no,
                    'street_name' => $resident->street_name,
                    'subdivision' => $resident->subdivision,
                    'sitio_purok_id' => $resident->sitio_purok_id,
                    'sitio_purok' => $resident->sitio_purok,
                    'complete_address' => $resident->complete_address,
                ]);

                $householdDetails = $details;

            } else {

                $householdDetails = null;
            }

            /*
            |--------------------------------------------------------------------------
            | Reset Existing Registration for Secretary Review
            |--------------------------------------------------------------------------
            */

            $registration->update([
                'requested_household_id' => $requestedHouseholdId,
                'household_details' => $householdDetails,
                'status' => ResidentHouseholdRegistration::STATUS_PENDING,
                'rejection_reason' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);

            return $resident->load([
                'household',
                'householdRegistration.requestedHousehold',
                'residentSectors.sector',
            ]);
        });
    }

}