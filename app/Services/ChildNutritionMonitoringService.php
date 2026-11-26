<?php

namespace App\Services;

use App\Models\ChildNutritionAssessment;
use App\Models\ChildNutritionMonitoring;
use App\Models\ChildNutritionSetting;
use App\Models\Resident;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChildNutritionMonitoringService
{
    private const RECORD_FIELDS = [
        'monitoring_year',
        'sequence_no',
        'caregiver_name',
        'birthweight',
        'low_birth_weight',
    ];

    private const ASSESSMENT_FIELDS = [
        'assessment_month',
        'assessment_date',
        'age_months',
        'weight',
        'height',
        'muac',
        'edema',
        'weight_for_age',
        'height_for_age',
        'weight_for_height',
        'muac_edema_status',
        'remarks',
    ];

    /*
    |--------------------------------------------------------------------------
    | Monitoring Records
    |--------------------------------------------------------------------------
    */

    public function paginate(
        User $user,
        array $filters = []
    ): LengthAwarePaginator {
        $this->ensureBarangay($user);

        return ChildNutritionMonitoring::query()
            ->with(['resident', 'creator'])
            ->withCount('assessments')
            ->where('barangay_id', $user->barangay_id)
            ->when($filters['monitoring_year'] ?? null, function ($query, $year) {
                $query->where('monitoring_year', $year);
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $search = trim($search);

                $query->whereHas('resident', function ($residentQuery) use ($search) {
                    $residentQuery->where(function ($q) use ($search) {
                        $q->whereRaw(
                            "CONCAT_WS(' ', first_name, middle_name, last_name, ext_name) LIKE ?",
                            ["%{$search}%"]
                        );
                    });
                });
            })
            ->orderByDesc('monitoring_year')
            ->orderBy('sequence_no')
            ->paginate(min(max((int) ($filters['per_page'] ?? 15), 1), 100));
    }

    /**
     * Retrieve all monitoring records for the printable annual report.
     */
    public function getReportRecords(
        User $user,
        int $monitoringYear
    ) {
        $this->ensureBarangay($user);

        return ChildNutritionMonitoring::query()
            ->with([
                'resident',
                'barangay',
                'assessments' => function ($query) {
                    $query->orderBy('assessment_month');
                },
            ])
            ->where('barangay_id', $user->barangay_id)
            ->where('monitoring_year', $monitoringYear)
            ->orderBy('sequence_no')
            ->orderBy('id')
            ->get();
    }

    public function find(User $user, int $id): ChildNutritionMonitoring
    {
        $this->ensureBarangay($user);

        return ChildNutritionMonitoring::query()
            ->with([
                'resident',
                'barangay',
                'creator',
                'updater',
                'assessments.creator',
                'assessments.updater',
            ])
            ->where('barangay_id', $user->barangay_id)
            ->findOrFail($id);
    }

    public function store(
        User $user,
        array $data
    ): ChildNutritionMonitoring {
        $this->ensureBarangay($user);

        return DB::transaction(function () use ($user, $data) {
            $resident = Resident::query()
                ->where('id', $data['resident_id'])
                ->where('barangay_id', $user->barangay_id)
                ->firstOrFail();

            $this->ensureEligibleResident($resident);

            // Check including soft-deleted records because the
            // resident/year combination has a unique database index.
            $existing = ChildNutritionMonitoring::withTrashed()
                ->where('resident_id', $resident->id)
                ->where('monitoring_year', $data['monitoring_year'])
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    throw ValidationException::withMessages([
                        'resident_id' => 'An annual monitoring record for this child already exists but has been deleted. Restore the existing record instead.',
                    ]);
                }

                throw ValidationException::withMessages([
                    'resident_id' => 'This child already has a monitoring record for the selected year.',
                ]);
            }

            $record = ChildNutritionMonitoring::create([
                ...Arr::only($data, self::RECORD_FIELDS),
                'resident_id' => $resident->id,
                'barangay_id' => $user->barangay_id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            return $record->load(['resident', 'creator']);
        });
    }

    public function update(
        User $user,
        int $id,
        array $data
    ): ChildNutritionMonitoring {
        $record = $this->findForUpdate($user, $id);

        $record->update([
            ...Arr::only($data, self::RECORD_FIELDS),
            'updated_by' => $user->id,
        ]);

        return $record->fresh([
            'resident',
            'creator',
            'updater',
            'assessments',
        ]);
    }

    public function delete(User $user, int $id): void
    {
        $record = $this->findForUpdate($user, $id);

        $record->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | Monthly Assessments
    |--------------------------------------------------------------------------
    */

    public function storeAssessment(
        User $user,
        int $recordId,
        array $data
    ): ChildNutritionAssessment {
        $record = $this->findForUpdate($user, $recordId);

        return DB::transaction(function () use ($user, $record, $data) {
            // A soft-deleted assessment still occupies its unique month.
            // Restore it instead of attempting to insert a duplicate.
            $existing = $record->assessments()
                ->withTrashed()
                ->where('assessment_month', $data['assessment_month'])
                ->first();

            if ($existing) {
                if (!$existing->trashed()) {
                    throw ValidationException::withMessages([
                        'assessment_month' => 'An assessment for this month already exists.',
                    ]);
                }

                $existing->restore();

                $existing->update([
                    ...Arr::only($data, self::ASSESSMENT_FIELDS),
                    'updated_by' => $user->id,
                ]);

                return $existing->fresh(['creator', 'updater']);
            }

            return $record->assessments()->create([
                ...Arr::only($data, self::ASSESSMENT_FIELDS),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ])->load('creator');
        });
    }

    public function updateAssessment(
        User $user,
        int $recordId,
        int $assessmentId,
        array $data
    ): ChildNutritionAssessment {
        $record = $this->findForUpdate($user, $recordId);

        $assessment = $record->assessments()
            ->findOrFail($assessmentId);

        $assessment->update([
            ...Arr::only($data, self::ASSESSMENT_FIELDS),
            'updated_by' => $user->id,
        ]);

        return $assessment->fresh(['creator', 'updater']);
    }

    public function deleteAssessment(
        User $user,
        int $recordId,
        int $assessmentId
    ): void {
        $record = $this->findForUpdate($user, $recordId);

        $record->assessments()
            ->findOrFail($assessmentId)
            ->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | Resident Search
    |--------------------------------------------------------------------------
    */

    public function searchResidents(
        User $user,
        array $filters = []
    ): LengthAwarePaginator {
        $this->ensureBarangay($user);

        $monitoringYear = (int) ($filters['monitoring_year'] ?? now()->year);

        $settings = ChildNutritionSetting::query()->first();

        return Resident::query()
            ->where('barangay_id', $user->barangay_id)
            ->whereNotNull('date_of_birth')
            ->when(
                $settings?->age_restriction_enabled,
                function ($query) use ($settings) {
                    $today = Carbon::today();

                    $minimumAge = (int) $settings->minimum_age_months;
                    $maximumAge = (int) $settings->maximum_age_months;

                    $query
                        ->whereDate(
                            'date_of_birth',
                            '<=',
                            $today->copy()->subMonths($minimumAge)
                        )
                        ->whereDate(
                            'date_of_birth',
                            '>',
                            $today->copy()->subMonths($maximumAge + 1)
                        );
                }
            )
            ->whereNotIn('id', function ($query) use ($monitoringYear) {
                $query->select('resident_id')
                    ->from('child_nutrition_monitorings')
                    ->where('monitoring_year', $monitoringYear);
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $search = trim($search);

                $query->where(function ($q) use ($search) {
                    $q->whereRaw(
                        "CONCAT_WS(' ', first_name, middle_name, last_name, ext_name) LIKE ?",
                        ["%{$search}%"]
                    )->orWhere('mobile_no', 'like', "%{$search}%");
                });
            })
            ->select([
                'id',
                'first_name',
                'middle_name',
                'last_name',
                'ext_name',
                'date_of_birth',
                'sex',
                'age',
                'sitio_purok',
                'mobile_no',
                'barangay_id',
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(min(max((int) ($filters['per_page'] ?? 15), 1), 100));
    }

    /*
    |--------------------------------------------------------------------------
    | Module Settings
    |--------------------------------------------------------------------------
    */

    public function getSettings(): ChildNutritionSetting
    {
        return ChildNutritionSetting::query()->first()
            ?? new ChildNutritionSetting([
                'age_restriction_enabled' => false,
                'minimum_age_months' => null,
                'maximum_age_months' => null,
            ]);
    }

    public function updateSettings(
        User $user,
        array $data
    ): ChildNutritionSetting {
        if (
            ($data['age_restriction_enabled'] ?? false) &&
            (
                !isset($data['minimum_age_months']) ||
                !isset($data['maximum_age_months'])
            )
        ) {
            throw ValidationException::withMessages([
                'minimum_age_months' => 'Both minimum and maximum ages are required when age restriction is enabled.',
            ]);
        }

        if (
            ($data['age_restriction_enabled'] ?? false) &&
            $data['minimum_age_months'] > $data['maximum_age_months']
        ) {
            throw ValidationException::withMessages([
                'maximum_age_months' => 'Maximum age must be greater than or equal to minimum age.',
            ]);
        }

        return DB::transaction(function () use ($user, $data) {
            $settings = ChildNutritionSetting::query()->first()
                ?? new ChildNutritionSetting();
                
            $settings->fill([
                'age_restriction_enabled' => $data['age_restriction_enabled'] ?? false,
                'minimum_age_months' => $data['minimum_age_months'] ?? null,
                'maximum_age_months' => $data['maximum_age_months'] ?? null,
                'updated_by' => $user->id,
            ]);

            $settings->save();

            return $settings->fresh(['updater']);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function findForUpdate(
        User $user,
        int $id
    ): ChildNutritionMonitoring {
        $this->ensureBarangay($user);

        return ChildNutritionMonitoring::query()
            ->where('barangay_id', $user->barangay_id)
            ->findOrFail($id);
    }

    private function ensureEligibleResident(Resident $resident): void
    {
        $settings = ChildNutritionSetting::query()->first();

        if (!$settings?->age_restriction_enabled) {
            return;
        }

        if (!$resident->date_of_birth) {
            throw ValidationException::withMessages([
                'resident_id' => 'The selected child has no date of birth recorded.',
            ]);
        }

        $ageMonths = (int) Carbon::parse($resident->date_of_birth)
            ->diffInMonths(Carbon::today());

        if (
            $ageMonths < $settings->minimum_age_months ||
            $ageMonths > $settings->maximum_age_months
        ) {
            throw ValidationException::withMessages([
                'resident_id' => 'The selected child does not meet the configured age eligibility.',
            ]);
        }
    }

    private function ensureBarangay(User $user): void
    {
        abort_unless(
            $user->barangay_id,
            403,
            'Your account is not assigned to a barangay.'
        );
    }
}
