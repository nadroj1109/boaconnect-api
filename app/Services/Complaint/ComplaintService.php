<?php

namespace App\Services\Complaint;

use App\Enums\ComplaintHearingStatus;
use App\Enums\ComplaintSource;
use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComplaintService
{
    /*
    |--------------------------------------------------------------------------
    | Complaint Creation
    |--------------------------------------------------------------------------
    */

    public function create(
        Resident $resident,
        User $submittedBy,
        ComplaintSource $source,
        array $data,
    ): Complaint {
        return DB::transaction(function () use (
            $resident,
            $submittedBy,
            $source,
            $data,
        ) {
            $complaint = Complaint::create([
                'complaint_no' =>
                    $this->generateComplaintNumber(),

                'barangay_id' =>
                    $resident->barangay_id,

                'resident_id' =>
                    $resident->id,

                'respondent_id' =>
                    $data['respondent_id'],

                'submitted_by' =>
                    $submittedBy->id,

                'source' =>
                    $source,

                'is_historical' => false,

                'complaint_type_id' =>
                    $data['complaint_type_id'],

                'subject' =>
                    $data['subject'],

                'description' =>
                    $data['description'],

                'incident_date' =>
                    $data['incident_date'],

                'incident_time' =>
                    $data['incident_time'] ?? null,

                'incident_location' =>
                    $data['incident_location'] ?? null,

                'status' =>
                    ComplaintStatus::SUBMITTED,
            ]);

            $complaint->logs()->create([
                'status' =>
                    ComplaintStatus::SUBMITTED,

                'remarks' =>
                    $source === ComplaintSource::RESIDENT
                        ? 'Complaint submitted by resident.'
                        : 'Complaint manually encoded by barangay staff.',

                'performed_by' =>
                    $submittedBy->id,
            ]);

            return $complaint->fresh([
                'barangay',
                'resident',
                'respondent',
                'submittedBy',
                'complaintType',
                'logs',
            ]);
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Complaint Update
    |--------------------------------------------------------------------------
    */

    public function update(
        Complaint $complaint,
        array $data,
    ): Complaint {
        return DB::transaction(function () use (
            $complaint,
            $data,
        ) {

            $barangayId =
                $complaint->barangay_id;


            if (array_key_exists(
                'resident_id',
                $data
            )) {

                $resident = Resident::query()
                    ->whereKey(
                        $data['resident_id']
                    )
                    ->firstOrFail();

                $barangayId =
                    $resident->barangay_id;

            }


            if (array_key_exists(
                'resident_id',
                $data
            )) {

                $data['barangay_id'] =
                    $barangayId;

            }


            if (array_key_exists(
                'respondent_id',
                $data
            )) {

                $respondent = Resident::query()
                    ->whereKey(
                        $data['respondent_id']
                    )
                    ->firstOrFail();


                if (
                    $respondent->barangay_id !==
                    $barangayId
                ) {

                    throw ValidationException::withMessages([
                        'respondent_id' =>
                            'The selected respondent does not belong to the complaint barangay.',
                    ]);

                }

            }


            $complaint->update(
                $data
            );


            return $complaint->fresh([
                'barangay',
                'resident',
                'respondent',
                'submittedBy',
                'complaintType',
                'assignedTo',
                'logs',
                'hearings',
                'attachments',
            ]);

        });
    }

    /*
    |--------------------------------------------------------------------------
    | Complaint Number
    |--------------------------------------------------------------------------
    */

    protected function generateComplaintNumber(): string
    {
        $year = now()->year;

        $lastComplaint = Complaint::query()
            ->whereYear(
                'created_at',
                $year
            )
            ->latest('id')
            ->lockForUpdate()
            ->first();

        $sequence = $lastComplaint
            ? (
                (int) substr(
                    $lastComplaint->complaint_no,
                    -6
                )
            ) + 1
            : 1;

        return sprintf(
            'CMP-%d-%06d',
            $year,
            $sequence
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Resident Lookup
    |--------------------------------------------------------------------------
    */

    public function findResidentForUser(
        User $user,
        int $residentId,
    ): Resident {
        $query = Resident::query()
            ->whereKey($residentId);

        if ($user->barangay_id !== null) {
            $query->where(
                'barangay_id',
                $user->barangay_id
            );
        }

        return $query->firstOrFail();
    }


    /*
    |--------------------------------------------------------------------------
    | Historical Complaint
    |--------------------------------------------------------------------------
    */

    public function createHistorical(
        Resident $resident,
        User $submittedBy,
        array $data,
    ): Complaint {
        return DB::transaction(function () use (
            $resident,
            $submittedBy,
            $data,
        ) {
            $status = ComplaintStatus::from(
                $data['status']
            );

            $complaint = Complaint::create([
                'complaint_no' =>
                    $data['complaint_no']
                    ?? $this->generateComplaintNumber(),

                'barangay_id' =>
                    $resident->barangay_id,

                'resident_id' =>
                    $resident->id,

                'respondent_id' =>
                    $data['respondent_id'] ?? null,

                'submitted_by' =>
                    $submittedBy->id,

                'source' =>
                    ComplaintSource::STAFF,

                'is_historical' =>
                    true,

                'complaint_type_id' =>
                    $data['complaint_type_id'],

                'subject' =>
                    $data['subject'],

                'description' =>
                    $data['description'],

                'incident_date' =>
                    $data['incident_date'],

                'incident_time' =>
                    $data['incident_time'] ?? null,

                'incident_location' =>
                    $data['incident_location'] ?? null,

                'status' =>
                    $status,

                'assigned_to' =>
                    $data['assigned_to'] ?? null,

                'resolution' =>
                    $data['resolution'] ?? null,

                'resolved_at' =>
                    $data['resolved_at'] ?? null,

                'closed_at' =>
                    $data['closed_at'] ?? null,
            ]);

            $complaint->logs()->create([
                'status' =>
                    $status,

                'remarks' =>
                    'Historical complaint record encoded into BOAConnect.',

                'performed_by' =>
                    $submittedBy->id,
            ]);

            return $complaint->fresh([
                'barangay',
                'resident',
                'respondent',
                'submittedBy',
                'complaintType',
                'assignedTo',
                'logs.performedBy',
                'hearings.scheduledBy',
                'attachments.uploadedBy',
            ]);
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Review
    |--------------------------------------------------------------------------
    |
    | SUBMITTED → UNDER_REVIEW
    |
    */

    public function review(
        Complaint $complaint,
        User $performedBy,
        array $data = [],
    ): Complaint {
        return DB::transaction(function () use (
            $complaint,
            $performedBy,
            $data,
        ) {
            $this->ensureStatus(
                $complaint,
                ComplaintStatus::SUBMITTED
            );

            $complaint->update([
                'status' =>
                    ComplaintStatus::UNDER_REVIEW,
            ]);

            $this->createLog(
                complaint: $complaint,
                status: ComplaintStatus::UNDER_REVIEW,
                performedBy: $performedBy,
                remarks: $data['remarks'] ?? null,
            );

            return $this->freshComplaint(
                $complaint
            );
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Assignment
    |--------------------------------------------------------------------------
    |
    | UNDER_REVIEW → ASSIGNED
    |
    */

    public function assign(
        Complaint $complaint,
        User $performedBy,
        User $assignedTo,
        array $data = [],
    ): Complaint {
        return DB::transaction(function () use (
            $complaint,
            $performedBy,
            $assignedTo,
            $data,
        ) {
            $this->ensureStatus(
                $complaint,
                ComplaintStatus::UNDER_REVIEW
            );

            /*
             * The assigned user must belong to
             * the same barangay as the complaint.
             */
            if (
                $assignedTo->barangay_id === null ||
                $assignedTo->barangay_id !==
                    $complaint->barangay_id
            ) {
                throw ValidationException::withMessages([
                    'assigned_to' =>
                        'The selected user does not belong to the complaint barangay.',
                ]);
            }

            $complaint->update([
                'assigned_to' =>
                    $assignedTo->id,

                'status' =>
                    ComplaintStatus::ASSIGNED,
            ]);

            $this->createLog(
                complaint: $complaint,
                status: ComplaintStatus::ASSIGNED,
                performedBy: $performedBy,
                remarks: $data['remarks'] ?? null,
            );

            return $this->freshComplaint(
                $complaint
            );
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Schedule Hearing
    |--------------------------------------------------------------------------
    |
    | ASSIGNED → SCHEDULED
    |
    */

    public function scheduleHearing(
        Complaint $complaint,
        User $performedBy,
        array $data,
    ): Complaint {
        return DB::transaction(function () use (
            $complaint,
            $performedBy,
            $data,
        ) {
            $this->ensureStatus(
                $complaint,
                ComplaintStatus::ASSIGNED
            );

            $complaint->hearings()->create([
                'hearing_date' =>
                    $data['hearing_date'],

                'hearing_time' =>
                    $data['hearing_time'],

                'location' =>
                    $data['location'],

                'status' =>
                    ComplaintHearingStatus::SCHEDULED,

                'remarks' =>
                    $data['remarks'] ?? null,

                'scheduled_by' =>
                    $performedBy->id,
            ]);

            $complaint->update([
                'status' =>
                    ComplaintStatus::SCHEDULED,
            ]);

            $this->createLog(
                complaint: $complaint,
                status: ComplaintStatus::SCHEDULED,
                performedBy: $performedBy,
                remarks:
                    $data['remarks']
                    ?? 'Hearing scheduled.',
            );

            return $this->freshComplaint(
                $complaint
            );
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Start Hearing
    |--------------------------------------------------------------------------
    |
    | SCHEDULED → HEARING
    |
    */

    public function startHearing(
        Complaint $complaint,
        User $performedBy,
        array $data = [],
    ): Complaint {
        return DB::transaction(function () use (
            $complaint,
            $performedBy,
            $data,
        ) {
            $this->ensureStatus(
                $complaint,
                ComplaintStatus::SCHEDULED
            );

            $hearing = $complaint->hearings()
                ->whereIn(
                    'status',
                    [
                        ComplaintHearingStatus::SCHEDULED,
                        ComplaintHearingStatus::RESCHEDULED,
                    ]
                )
                ->latest('hearing_date')
                ->latest('id')
                ->first();

            if (! $hearing) {
                throw ValidationException::withMessages([
                    'hearing' =>
                        'There is no scheduled hearing available to start.',
                ]);
            }

            $complaint->update([
                'status' =>
                    ComplaintStatus::HEARING,
            ]);

            $this->createLog(
                complaint: $complaint,
                status: ComplaintStatus::HEARING,
                performedBy: $performedBy,
                remarks: $data['remarks'] ?? null,
            );

            return $this->freshComplaint(
                $complaint
            );
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Resolve
    |--------------------------------------------------------------------------
    |
    | HEARING → RESOLVED
    |
    */

    public function resolve(
        Complaint $complaint,
        User $performedBy,
        array $data,
    ): Complaint {
        return DB::transaction(function () use (
            $complaint,
            $performedBy,
            $data,
        ) {
            $this->ensureStatus(
                $complaint,
                ComplaintStatus::HEARING
            );

            $complaint->update([
                'status' =>
                    ComplaintStatus::RESOLVED,

                'resolution' =>
                    $data['resolution'],

                'resolved_at' =>
                    now(),
            ]);

            $this->createLog(
                complaint: $complaint,
                status: ComplaintStatus::RESOLVED,
                performedBy: $performedBy,
                remarks: $data['remarks'] ?? null,
            );

            return $this->freshComplaint(
                $complaint
            );
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Close
    |--------------------------------------------------------------------------
    |
    | RESOLVED → CLOSED
    |
    */

    public function close(
        Complaint $complaint,
        User $performedBy,
        array $data = [],
    ): Complaint {
        return DB::transaction(function () use (
            $complaint,
            $performedBy,
            $data,
        ) {
            $this->ensureStatus(
                $complaint,
                ComplaintStatus::RESOLVED
            );

            $complaint->update([
                'status' =>
                    ComplaintStatus::CLOSED,

                'closed_at' =>
                    now(),
            ]);

            $this->createLog(
                complaint: $complaint,
                status: ComplaintStatus::CLOSED,
                performedBy: $performedBy,
                remarks: $data['remarks'] ?? null,
            );

            return $this->freshComplaint(
                $complaint
            );
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Reject
    |--------------------------------------------------------------------------
    |
    | SUBMITTED / UNDER_REVIEW → REJECTED
    |
    */

    public function reject(
        Complaint $complaint,
        User $performedBy,
        array $data = [],
    ): Complaint {
        return DB::transaction(function () use (
            $complaint,
            $performedBy,
            $data,
        ) {
            $this->ensureStatusIn(
                $complaint,
                [
                    ComplaintStatus::SUBMITTED,
                    ComplaintStatus::UNDER_REVIEW,
                ]
            );

            $complaint->update([
                'status' =>
                    ComplaintStatus::REJECTED,
            ]);

            $this->createLog(
                complaint: $complaint,
                status: ComplaintStatus::REJECTED,
                performedBy: $performedBy,
                remarks: $data['remarks'] ?? null,
            );

            return $this->freshComplaint(
                $complaint
            );
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Cancel Complaint
    |--------------------------------------------------------------------------
    |
    | SUBMITTED / UNDER_REVIEW / ASSIGNED /
    | SCHEDULED / HEARING → CANCELLED
    |
    */

    public function cancel(
        Complaint $complaint,
        User $performedBy,
        array $data = [],
    ): Complaint {
        return DB::transaction(function () use (
            $complaint,
            $performedBy,
            $data,
        ) {
            $this->ensureStatusIn(
                $complaint,
                [
                    ComplaintStatus::SUBMITTED,
                    ComplaintStatus::UNDER_REVIEW,
                    ComplaintStatus::ASSIGNED,
                    ComplaintStatus::SCHEDULED,
                    ComplaintStatus::HEARING,
                ]
            );

            $complaint->update([
                'status' =>
                    ComplaintStatus::CANCELLED,
            ]);

            $this->createLog(
                complaint: $complaint,
                status: ComplaintStatus::CANCELLED,
                performedBy: $performedBy,
                remarks: $data['remarks'] ?? null,
            );

            return $this->freshComplaint(
                $complaint
            );
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Complete Hearing
    |--------------------------------------------------------------------------
    |
    | Hearing remains under complaint HEARING status.
    |
    */

    public function completeHearing(
        Complaint $complaint,
        int $hearingId,
        User $performedBy,
        array $data = [],
    ): Complaint {
        return DB::transaction(function () use (
            $complaint,
            $hearingId,
            $performedBy,
            $data,
        ) {
            $this->ensureStatus(
                $complaint,
                ComplaintStatus::HEARING
            );

            $hearing = $complaint->hearings()
                ->whereKey($hearingId)
                ->first();

            if (! $hearing) {
                throw ValidationException::withMessages([
                    'hearing' =>
                        'The selected hearing does not belong to this complaint.',
                ]);
            }

            if (
                ! in_array(
                    $hearing->status,
                    [
                        ComplaintHearingStatus::SCHEDULED,
                        ComplaintHearingStatus::RESCHEDULED,
                    ],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'hearing' =>
                        'This hearing cannot be completed.',
                ]);
            }

            $hearing->update([
                'status' =>
                    ComplaintHearingStatus::COMPLETED,

                'remarks' =>
                    $data['remarks']
                    ?? $hearing->remarks,
            ]);

            $this->createLog(
                complaint: $complaint,
                status: ComplaintStatus::HEARING,
                performedBy: $performedBy,
                remarks:
                    $data['remarks']
                    ?? 'Hearing completed.',
            );

            return $this->freshComplaint(
                $complaint
            );
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Cancel Hearing
    |--------------------------------------------------------------------------
    |
    | The complaint remains in its current state.
    |
    */

    public function cancelHearing(
        Complaint $complaint,
        int $hearingId,
        User $performedBy,
        array $data = [],
    ): Complaint {
        return DB::transaction(function () use (
            $complaint,
            $hearingId,
            $performedBy,
            $data,
        ) {
            $this->ensureStatusIn(
                $complaint,
                [
                    ComplaintStatus::SCHEDULED,
                    ComplaintStatus::HEARING,
                ]
            );

            $hearing = $complaint->hearings()
                ->whereKey($hearingId)
                ->first();

            if (! $hearing) {
                throw ValidationException::withMessages([
                    'hearing' =>
                        'The selected hearing does not belong to this complaint.',
                ]);
            }

            if (
                ! in_array(
                    $hearing->status,
                    [
                        ComplaintHearingStatus::SCHEDULED,
                        ComplaintHearingStatus::RESCHEDULED,
                    ],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'hearing' =>
                        'This hearing cannot be cancelled.',
                ]);
            }

            $hearing->update([
                'status' =>
                    ComplaintHearingStatus::CANCELLED,

                'remarks' =>
                    $data['remarks']
                    ?? $hearing->remarks,
            ]);

            $this->createLog(
                complaint: $complaint,
                status: $complaint->status,
                performedBy: $performedBy,
                remarks:
                    $data['remarks']
                    ?? 'Hearing cancelled.',
            );

            return $this->freshComplaint(
                $complaint
            );
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Reschedule Hearing
    |--------------------------------------------------------------------------
    |
    | Complaint remains SCHEDULED.
    |
    */

    public function rescheduleHearing(
        Complaint $complaint,
        int $hearingId,
        User $performedBy,
        array $data,
    ): Complaint {
        return DB::transaction(function () use (
            $complaint,
            $hearingId,
            $performedBy,
            $data,
        ) {
            $this->ensureStatus(
                $complaint,
                ComplaintStatus::SCHEDULED
            );

            $hearing = $complaint->hearings()
                ->whereKey($hearingId)
                ->first();

            if (! $hearing) {
                throw ValidationException::withMessages([
                    'hearing' =>
                        'The selected hearing does not belong to this complaint.',
                ]);
            }

            if (
                ! in_array(
                    $hearing->status,
                    [
                        ComplaintHearingStatus::SCHEDULED,
                        ComplaintHearingStatus::RESCHEDULED,
                    ],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'hearing' =>
                        'This hearing cannot be rescheduled.',
                ]);
            }

            $hearing->update([
                'hearing_date' =>
                    $data['hearing_date'],

                'hearing_time' =>
                    $data['hearing_time'],

                'location' =>
                    $data['location'],

                'status' =>
                    ComplaintHearingStatus::RESCHEDULED,

                'remarks' =>
                    $data['remarks'] ?? null,
            ]);

            $this->createLog(
                complaint: $complaint,
                status: ComplaintStatus::SCHEDULED,
                performedBy: $performedBy,
                remarks:
                    $data['remarks']
                    ?? 'Hearing rescheduled.',
            );

            return $this->freshComplaint(
                $complaint
            );
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */

    protected function createLog(
        Complaint $complaint,
        ComplaintStatus $status,
        User $performedBy,
        ?string $remarks = null,
    ): void {
        $complaint->logs()->create([
            'status' =>
                $status,

            'remarks' =>
                $remarks,

            'performed_by' =>
                $performedBy->id,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Status Validation
    |--------------------------------------------------------------------------
    */

    protected function ensureStatus(
        Complaint $complaint,
        ComplaintStatus $expected,
    ): void {
        if ($complaint->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'This complaint cannot be processed while its status is "%s".',
                    $complaint->status?->label()
                        ?? $complaint->status?->value
                ),
            ]);
        }
    }


    protected function ensureStatusIn(
        Complaint $complaint,
        array $expectedStatuses,
    ): void {
        if (
            ! in_array(
                $complaint->status,
                $expectedStatuses,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'This action is not allowed while the complaint status is "%s".',
                    $complaint->status?->label()
                        ?? $complaint->status?->value
                ),
            ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Fresh Complaint
    |--------------------------------------------------------------------------
    */

    protected function freshComplaint(
        Complaint $complaint,
    ): Complaint {
        return $complaint->fresh([
            'barangay',
            'resident',
            'respondent',
            'submittedBy',
            'complaintType',
            'assignedTo',
            'logs.performedBy',
            'hearings.scheduledBy',
            'attachments.uploadedBy',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Respondent Complaint History
    |--------------------------------------------------------------------------
    */

    public function respondentHistory(
        Resident $resident
    ) {
        return Complaint::query()
            ->with([
                'complaintType',
            ])
            ->where(
                'respondent_id',
                $resident->id
            )
            ->latest('created_at')
            ->get();
    }
}