<?php

namespace App\Services;

use App\Models\PrenatalRecord;
use App\Models\PrenatalVisit;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PrenatalRecordService
{
    private const RECORD_FIELDS = [
        'registration_date',
        'spouse_name',
        'ob_score',
        'lmp',
        'edc',
        'height',
        'initial_weight',
        'bmi',
        'past_medical_history',
        'family_history',
        'tt1_date',
        'tt2_date',
        'tt3_date',
        'tt4_date',
        'tt5_date',
        'pregnancy_outcome',
        'remarks',
    ];

    private const VISIT_FIELDS = [
        'visit_number',
        'visit_date',
        'aog_weeks',
        'aog_days',
        'blood_pressure',
        'weight',
        'fundal_height',
        'fetal_heart_beat',
        'presentation',
        'medicine_given',
        'danger_signs',
        'hbsag',
        'hiv_screening',
        'gestational_diabetes',
        'cbc_hgb_hct',
        'rpr_rdt',
        'remarks',
    ];

    public function paginate(
        User $user,
        array $filters = []
    ): LengthAwarePaginator {
        $this->ensureBarangay($user);

        return PrenatalRecord::query()
            ->with(['resident', 'creator'])
            ->withCount('visits')
            ->where('barangay_id', $user->barangay_id)
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->whereHas('resident', function ($residentQuery) use ($search) {
                    $residentQuery->where(function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('middle_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
                });
            })
            ->when($filters['registration_date'] ?? null, function ($query, $date) {
                $query->whereDate('registration_date', $date);
            })
            ->latest('registration_date')
            ->paginate(min(max((int) ($filters['per_page'] ?? 15), 1), 100));
    }

    public function find(User $user, int $id): PrenatalRecord
    {
        $this->ensureBarangay($user);

        return PrenatalRecord::query()
            ->with([
                'resident',
                'barangay',
                'creator',
                'updater',
                'visits.creator',
                'visits.updater',
            ])
            ->where('barangay_id', $user->barangay_id)
            ->findOrFail($id);
    }

    public function store(User $user, array $data): PrenatalRecord
    {
        $this->ensureBarangay($user);

        return DB::transaction(function () use ($user, $data) {
            $resident = Resident::query()
                ->where('id', $data['resident_id'])
                ->where('barangay_id', $user->barangay_id)
                ->firstOrFail();

            $record = PrenatalRecord::create([
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
    ): PrenatalRecord {
        $record = $this->findForUpdate($user, $id);

        $record->update([
            ...Arr::only($data, self::RECORD_FIELDS),
            'updated_by' => $user->id,
        ]);

        return $record->fresh([
            'resident',
            'creator',
            'updater',
            'visits',
        ]);
    }

    public function delete(User $user, int $id): void
    {
        $record = $this->findForUpdate($user, $id);

        $record->delete();
    }

    public function storeVisit(
        User $user,
        int $recordId,
        array $data
    ): PrenatalVisit {
        $record = $this->findForUpdate($user, $recordId);

        return $record->visits()->create([
            ...Arr::only($data, self::VISIT_FIELDS),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ])->load('creator');
    }

    public function updateVisit(
        User $user,
        int $recordId,
        int $visitId,
        array $data
    ): PrenatalVisit {
        $record = $this->findForUpdate($user, $recordId);

        $visit = $record->visits()->findOrFail($visitId);

        $visit->update([
            ...Arr::only($data, self::VISIT_FIELDS),
            'updated_by' => $user->id,
        ]);

        return $visit->fresh(['creator', 'updater']);
    }

    public function deleteVisit(
        User $user,
        int $recordId,
        int $visitId
    ): void {
        $record = $this->findForUpdate($user, $recordId);

        $record->visits()->findOrFail($visitId)->delete();
    }

    private function findForUpdate(
        User $user,
        int $id
    ): PrenatalRecord {
        $this->ensureBarangay($user);

        return PrenatalRecord::query()
            ->where('barangay_id', $user->barangay_id)
            ->findOrFail($id);
    }

    private function ensureBarangay(User $user): void
    {
        abort_unless($user->barangay_id, 403, 'Your account is not assigned to a barangay.');
    }

    public function searchResidents(
        User $user,
        array $filters = []
    ): LengthAwarePaginator {
        $this->ensureBarangay($user);

        return Resident::query()
            ->where('barangay_id', $user->barangay_id)
            ->where('sex', 'female')
            ->where('pregnant_woman', true)
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
                'age',
                'civil_status',
                'sitio_purok',
                'mobile_no',
                'height',
                'weight',
                'barangay_id',
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(min(max((int) ($filters['per_page'] ?? 15), 1), 100));
    }

}
