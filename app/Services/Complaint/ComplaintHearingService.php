<?php

namespace App\Services\Complaint;

use App\Enums\ComplaintHearingStatus;
use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\ComplaintHearing;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComplaintHearingService
{
    public function complete(
        ComplaintHearing $hearing,
        User $user,
        ?string $remarks = null,
    ): ComplaintHearing {
        return DB::transaction(function () use (
            $hearing,
            $user,
            $remarks,
        ) {
            $hearing = $this->lockHearing($hearing);

            $this->ensureStatus(
                $hearing,
                [
                    ComplaintHearingStatus::SCHEDULED,
                ]
            );

            $hearing->update([
                'status' =>
                    ComplaintHearingStatus::COMPLETED,

                'remarks' =>
                    $remarks,
            ]);

            return $this->fresh($hearing);
        });
    }

    public function cancel(
        ComplaintHearing $hearing,
        User $user,
        ?string $remarks = null,
    ): ComplaintHearing {
        return DB::transaction(function () use (
            $hearing,
            $user,
            $remarks,
        ) {
            $hearing = $this->lockHearing($hearing);

            $this->ensureStatus(
                $hearing,
                [
                    ComplaintHearingStatus::SCHEDULED,
                    ComplaintHearingStatus::RESCHEDULED,
                ]
            );

            $hearing->update([
                'status' =>
                    ComplaintHearingStatus::CANCELLED,

                'remarks' =>
                    $remarks,
            ]);

            return $this->fresh($hearing);
        });
    }

    public function reschedule(
        ComplaintHearing $hearing,
        User $user,
        string $hearingDate,
        string $hearingTime,
        string $location,
        ?string $remarks = null,
    ): ComplaintHearing {
        return DB::transaction(function () use (
            $hearing,
            $user,
            $hearingDate,
            $hearingTime,
            $location,
            $remarks,
        ) {
            $hearing = $this->lockHearing($hearing);

            $this->ensureStatus(
                $hearing,
                [
                    ComplaintHearingStatus::SCHEDULED,
                ]
            );

            /*
             * Keep the original hearing record as history.
             */
            $hearing->update([
                'status' =>
                    ComplaintHearingStatus::RESCHEDULED,

                'remarks' =>
                    $remarks,
            ]);

            /*
             * Create a new hearing record for
             * the rescheduled hearing.
             */
            $newHearing = ComplaintHearing::create([
                'complaint_id' =>
                    $hearing->complaint_id,

                'hearing_date' =>
                    $hearingDate,

                'hearing_time' =>
                    $hearingTime,

                'location' =>
                    $location,

                'status' =>
                    ComplaintHearingStatus::SCHEDULED,

                'remarks' =>
                    $remarks,

                'scheduled_by' =>
                    $user->id,
            ]);

            return $this->fresh($newHearing);
        });
    }

    public function createHistorical(
        Complaint $complaint,
        User $user,
        array $data,
    ): ComplaintHearing {
        return DB::transaction(function () use (
            $complaint,
            $user,
            $data,
        ) {
            if (! $complaint->is_historical) {
                throw ValidationException::withMessages([
                    'complaint' =>
                        'Historical hearings can only be added to historical complaints.',
                ]);
            }

            if (
                $user->barangay_id !== null
                && $user->barangay_id !==
                    $complaint->barangay_id
            ) {
                return $this->error(
                    'You are not authorized to access this complaint.',
                    [],
                    403
                );
            }

            $status = ComplaintHearingStatus::from(
                $data['status']
            );

            $hearing = ComplaintHearing::create([
                'complaint_id' =>
                    $complaint->id,

                'hearing_date' =>
                    $data['hearing_date'],

                'hearing_time' =>
                    $data['hearing_time'],

                'location' =>
                    $data['location'],

                'status' =>
                    $status,

                'remarks' =>
                    $data['remarks'] ?? null,

                'scheduled_by' =>
                    $user->id,
            ]);

            return $this->fresh($hearing);
        });
    }

    protected function lockHearing(
        ComplaintHearing $hearing,
    ): ComplaintHearing {
        return ComplaintHearing::query()
            ->whereKey($hearing->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    protected function ensureStatus(
        ComplaintHearing $hearing,
        array $allowedStatuses,
    ): void {
        if (
            ! in_array(
                $hearing->status,
                $allowedStatuses,
                true
            )
        ) {
            $allowed = collect($allowedStatuses)
                ->map(
                    fn (
                        ComplaintHearingStatus $status
                    ) => $status->label()
                )
                ->join(', ');

            throw ValidationException::withMessages([
                'status' =>
                    'This hearing cannot be processed from its current status. ' .
                    "Expected status: {$allowed}.",
            ]);
        }

        if ($hearing->complaint?->is_historical) {
            throw ValidationException::withMessages([
                'status' =>
                    'Historical complaint hearings cannot be processed through the normal hearing workflow.',
            ]);
        }
    }

    protected function fresh(
        ComplaintHearing $hearing,
    ): ComplaintHearing {
        return $hearing->fresh([
            'complaint',
            'scheduledBy',
        ]);
    }
}