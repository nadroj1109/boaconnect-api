<?php

namespace App\Services;

use App\Models\Household;
use App\Models\Resident;
use App\Models\ResidentHouseholdRegistration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class ResidentService
{

    public function paginate(array $filters): LengthAwarePaginator
    {
        return Resident::query()
            ->when(
                $filters['household_id'] ?? null,

                fn ($query, $householdId) =>

                    $query->where('household_id', $householdId)

            )
            ->when(
                $filters['search'] ?? null,
                function ($query, $search) {
                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('last_name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%");
                    });
                }
            )
            ->orderBy(
                $filters['sort'] ?? 'last_name',
                $filters['direction'] ?? 'asc'
            )
            ->paginate(
                $filters['per_page'] ?? 10
            );
    }

    public function store(array $data): Resident
    {

        return DB::transaction(function () use ($data) {

            if ($data['is_household_head'] ?? false) {

                $this->validateHouseholdHead(
                    $data['household_id']
                );

            }

            $sectors = $data['sectors'] ?? [];

            unset($data['sectors']);

            $resident = Resident::create([
                ...$data,
                'is_household_head' => $data['is_household_head'] ?? false,
            ]);

            $this->syncResidentSectors(
                $resident,
                $sectors
            );

            return $resident->load(
                'residentSectors.sector'
            );

        });
    }

    public function show(Resident $resident): Resident
    {
        return $resident->load([
            'ethnicity',
            'residentSectors',
            'household',
            'householdRegistration.requestedHousehold',
        ]);
    }

    public function update(Resident $resident, array $data): Resident
    {
        return DB::transaction(function () use (
            $resident,
            $data
        ) {

            /*
            |--------------------------------------------------------------------------
            | Ensure only one Household Head per Household
            |--------------------------------------------------------------------------
            */

            if ($data['is_household_head'] ?? false) {

                $this->validateHouseholdHead(
                    $resident->household_id,
                    $resident->id
                );
            }

            $sectors = $data['sectors'] ?? [];

            unset($data['sectors']);

            $resident->update($data);

            $this->syncResidentSectors(
                $resident,
                $sectors
            );

            return $resident->refresh()
                ->load(
                    'residentSectors.sector'
                );

        });
    }

    private function validateHouseholdHead(
        int $householdId,
        ?int $ignoreResidentId = null
    ): void {

        $query = Resident::where(
            'household_id',
            $householdId
        )
        ->where('is_household_head', true);

        if ($ignoreResidentId) {

            $query->where(
                'id',
                '!=',
                $ignoreResidentId
            );

        }

        if ($query->exists()) {

            throw ValidationException::withMessages([

                'is_household_head' =>

                    'This household already has a designated household head.',

            ]);

        }

    }

    public function syncResidentSectors(
        Resident $resident,
        array $sectors
    ): void {

        $resident->residentSectors()->delete();

        foreach ($sectors as $sector) {

            $resident->residentSectors()->create([

                'sector_id' => $sector['sector_id'],

                'registered' => $sector['registered'] ?? null,

            ]);

        }

    }

    /**
     * Create a resident registration without household assignment.
     *
     * Called by the public resident registration flow.
     */
    public function storeUnassignedRegistration(array $data): Resident
    {
        $sectors = $data['sectors'] ?? [];

        unset($data['sectors']);

        $resident = Resident::create([
            ...$data,
            'household_id' => null,
            'is_household_head' => false,
        ]);

        $this->syncResidentSectors($resident, $sectors);

        return $resident->load('residentSectors.sector');
    }

    public function availableResidentsForUser(int $barangayId)
    {
        return Resident::query()
            ->whereHas('household', function ($household) use ($barangayId) {
                $household->where('barangay_id', $barangayId);
            })
            ->whereDoesntHave('user')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn ($resident) => [
                'id' => $resident->id,
                'value' => $resident->id,
                'label' => $resident->full_name,
                'email' => $resident->email_address,
                'mobile_number' => $resident->mobile_no,
            ])
            ->values();
    }
    
    public function residentRegistrations(array $filters): LengthAwarePaginator
    {
        return Resident::query()
            ->where('barangay_id', Auth::user()->barangay_id)
            ->whereNull('household_id')
            ->whereHas('householdRegistration', function ($query) {
                $query->where(
                    'status',
                    ResidentHouseholdRegistration::STATUS_PENDING
                );
            })
            ->when(
                $filters['search'] ?? null,
                function ($query, $search) {
                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('last_name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%");
                    });
                }
            )
            ->orderBy(
                $filters['sort'] ?? 'last_name',
                $filters['direction'] ?? 'asc'
            )
            ->paginate($filters['per_page'] ?? 10);
    }

    public function updateHouseholdResident(
        Resident $resident,
        array $data
    ): Resident {
        return DB::transaction(function () use ($resident, $data) {

            $user = Auth::user();

            // Lock the resident to prevent simultaneous approval.
            $resident = Resident::query()
                ->whereKey($resident->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Enforce barangay ownership.
            if (
                !$user->barangay_id ||
                $resident->barangay_id !== $user->barangay_id
            ) {
                throw ValidationException::withMessages([
                    'resident' => 'You are not authorized to review this resident registration.',
                ]);
            }

            // Lock the registration record.
            $registration = ResidentHouseholdRegistration::query()
                ->where('resident_id', $resident->id)
                ->lockForUpdate()
                ->first();

            if (!$registration) {
                throw ValidationException::withMessages([
                    'registration' => 'Resident registration details were not found.',
                ]);
            }

            if ($registration->status !== ResidentHouseholdRegistration::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'registration' => 'This resident registration is not pending review.',
                ]);
            }

            if ($resident->household_id !== null) {
                throw ValidationException::withMessages([
                    'registration' => 'This resident is already assigned to a household.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Household Head Registration
            |--------------------------------------------------------------------------
            */

            if ($registration->requested_household_id === null) {

                $details = $registration->household_details;

                if (!is_array($details) || empty($details)) {
                    throw ValidationException::withMessages([
                        'household_details' => 'Household details are missing.',
                    ]);
                }

                if (empty($data['household_no'])) {
                    throw ValidationException::withMessages([
                        'household_no' => 'A household number is required.',
                    ]);
                }

                // Only copy fields that belong to the households table.
                $householdFields = [
                    'region_id',
                    'province_id',
                    'city_id',
                    'zip_code',
                    'sitio_purok',
                    'house_no',
                    'street_name',
                    'subdivision',
                    'complete_address',
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
                ];

                $householdData = array_intersect_key(
                    $details,
                    array_flip($householdFields)
                );

                $household = Household::create([
                    ...$householdData,
                    'barangay_id' => $resident->barangay_id,
                    'sitio_purok_id' => $resident->sitio_purok_id,
                    'household_no' => $data['household_no'],
                ]);

                $resident->update([
                    'household_id' => $household->id,
                    'is_household_head' => true,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Non-Head Registration
            |--------------------------------------------------------------------------
            */

            else {

                $household = Household::query()
                    ->whereKey($registration->requested_household_id)
                    ->where('barangay_id', $user->barangay_id)
                    ->lockForUpdate()
                    ->first();

                if (!$household) {
                    throw ValidationException::withMessages([
                        'household' => 'The requested household does not exist in your barangay.',
                    ]);
                }

                $resident->update([
                    'household_id' => $household->id,
                    'is_household_head' => false,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Mark Registration as Reviewed
            |--------------------------------------------------------------------------
            */

            $registration->update([
                'status' => ResidentHouseholdRegistration::STATUS_APPROVED,
                'rejection_reason' => null,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);

            return $resident->refresh()->load([
                'household',
                'residentSectors.sector',
            ]);
        });
    }

    public function rejectHouseholdResident(
        Resident $resident,
        string $rejectionReason
    ): Resident {
        return DB::transaction(function () use ($resident, $rejectionReason) {

            $user = Auth::user();

            $resident = Resident::query()
                ->whereKey($resident->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                !$user->barangay_id ||
                $resident->barangay_id !== $user->barangay_id
            ) {
                throw ValidationException::withMessages([
                    'resident' => 'You are not authorized to review this resident registration.',
                ]);
            }

            if ($resident->household_id !== null) {
                throw ValidationException::withMessages([
                    'registration' => 'This resident is already assigned to a household.',
                ]);
            }

            $registration = ResidentHouseholdRegistration::query()
                ->where('resident_id', $resident->id)
                ->lockForUpdate()
                ->first();

            if (!$registration) {
                throw ValidationException::withMessages([
                    'registration' => 'Resident registration details were not found.',
                ]);
            }

            if ($registration->status !== ResidentHouseholdRegistration::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'registration' => 'This resident registration is not pending review.',
                ]);
            }

            $registration->update([
                'status' => ResidentHouseholdRegistration::STATUS_REJECTED,
                'rejection_reason' => $rejectionReason,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);

            return $resident->refresh()->load([
                'household',
                'householdRegistration.requestedHousehold',
                'residentSectors.sector',
            ]);
        });
    }

}