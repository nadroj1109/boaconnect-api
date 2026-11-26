<?php

namespace App\Services\Complaint;

use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComplaintWorkflowService
{
    public function markUnderReview(
        Complaint $complaint,
        User $user,
        ?string $remarks = null,
    ): Complaint {
        return $this->transition(
            complaint: $complaint,
            user: $user,
            from: [
                ComplaintStatus::SUBMITTED,
            ],
            to: ComplaintStatus::UNDER_REVIEW,
            remarks: $remarks ??
                'Complaint is now under review.',
        );
    }

    public function assign(
        Complaint $complaint,
        User $user,
        User $assignee,
        ?string $remarks = null,
    ): Complaint {
        return DB::transaction(function () use (
            $complaint,
            $user,
            $assignee,
            $remarks,
        ) {
            $complaint = $this->lockComplaint(
                $complaint
            );

            $this->ensureStatus(
                $complaint,
                [
                    ComplaintStatus::UNDER_REVIEW,
                ]
            );

            $complaint->assigned_to =
                $assignee->id;

            $complaint->status =
                ComplaintStatus::ASSIGNED;

            $complaint->save();

            $this->log(
                complaint: $complaint,
                user: $user,
                status: ComplaintStatus::ASSIGNED,
                remarks: $remarks ??
                    'Complaint assigned to ' .
                    $assignee->name . '.',
            );

            return $this->fresh($complaint);
        });
    }

    public function schedule(
        Complaint $complaint,
        User $user,
        string $hearingDate,
        string $hearingTime,
        string $location,
        ?string $remarks = null,
    ): Complaint {
        return DB::transaction(function () use (
            $complaint,
            $user,
            $hearingDate,
            $hearingTime,
            $location,
            $remarks,
        ) {
            $complaint = $this->lockComplaint(
                $complaint
            );

            $this->ensureStatus(
                $complaint,
                [
                    ComplaintStatus::ASSIGNED,
                ]
            );

            $complaint->hearings()->create([
                'hearing_date' =>
                    $hearingDate,

                'hearing_time' =>
                    $hearingTime,

                'location' =>
                    $location,

                'status' =>
                    'scheduled',

                'remarks' =>
                    $remarks,

                'scheduled_by' =>
                    $user->id,
            ]);

            $complaint->status =
                ComplaintStatus::SCHEDULED;

            $complaint->save();

            $this->log(
                complaint: $complaint,
                user: $user,
                status: ComplaintStatus::SCHEDULED,
                remarks: $remarks ??
                    'Complaint hearing scheduled.',
            );

            return $this->fresh($complaint);
        });
    }

    public function startHearing(
        Complaint $complaint,
        User $user,
        ?string $remarks = null,
    ): Complaint {
        return $this->transition(
            complaint: $complaint,
            user: $user,
            from: [
                ComplaintStatus::SCHEDULED,
            ],
            to: ComplaintStatus::HEARING,
            remarks: $remarks ??
                'Complaint hearing has started.',
        );
    }

    public function resolve(
        Complaint $complaint,
        User $user,
        string $resolution,
        ?string $remarks = null,
    ): Complaint {
        return DB::transaction(function () use (
            $complaint,
            $user,
            $resolution,
            $remarks,
        ) {
            $complaint = $this->lockComplaint(
                $complaint
            );

            $this->ensureStatus(
                $complaint,
                [
                    ComplaintStatus::HEARING,
                ]
            );

            $complaint->status =
                ComplaintStatus::RESOLVED;

            $complaint->resolution =
                $resolution;

            $complaint->resolved_at =
                now();

            $complaint->save();

            $this->log(
                complaint: $complaint,
                user: $user,
                status: ComplaintStatus::RESOLVED,
                remarks: $remarks ??
                    'Complaint has been resolved.',
            );

            return $this->fresh($complaint);
        });
    }

    public function close(
        Complaint $complaint,
        User $user,
        ?string $remarks = null,
    ): Complaint {
        return $this->transition(
            complaint: $complaint,
            user: $user,
            from: [
                ComplaintStatus::RESOLVED,
            ],
            to: ComplaintStatus::CLOSED,
            remarks: $remarks ??
                'Complaint has been closed.',
        );
    }

    public function reject(
        Complaint $complaint,
        User $user,
        ?string $remarks = null,
    ): Complaint {
        return DB::transaction(function () use (
            $complaint,
            $user,
            $remarks,
        ) {
            $complaint = $this->lockComplaint(
                $complaint
            );

            $this->ensureStatus(
                $complaint,
                [
                    ComplaintStatus::SUBMITTED,
                    ComplaintStatus::UNDER_REVIEW,
                ]
            );

            $complaint->status =
                ComplaintStatus::REJECTED;

            $complaint->save();

            $this->log(
                complaint: $complaint,
                user: $user,
                status: ComplaintStatus::REJECTED,
                remarks: $remarks ??
                    'Complaint has been rejected.',
            );

            return $this->fresh($complaint);
        });
    }

    public function cancel(
        Complaint $complaint,
        User $user,
        ?string $remarks = null,
    ): Complaint {
        return DB::transaction(function () use (
            $complaint,
            $user,
            $remarks,
        ) {
            $complaint = $this->lockComplaint(
                $complaint
            );

            $this->ensureStatus(
                $complaint,
                [
                    ComplaintStatus::SUBMITTED,
                    ComplaintStatus::UNDER_REVIEW,
                    ComplaintStatus::ASSIGNED,
                    ComplaintStatus::SCHEDULED,
                    ComplaintStatus::HEARING,
                ]
            );

            $complaint->status =
                ComplaintStatus::CANCELLED;

            $complaint->save();

            $this->log(
                complaint: $complaint,
                user: $user,
                status: ComplaintStatus::CANCELLED,
                remarks: $remarks ??
                    'Complaint has been cancelled.',
            );

            return $this->fresh($complaint);
        });
    }

    protected function transition(
        Complaint $complaint,
        User $user,
        array $from,
        ComplaintStatus $to,
        string $remarks,
    ): Complaint {
        return DB::transaction(function () use (
            $complaint,
            $user,
            $from,
            $to,
            $remarks,
        ) {
            $complaint = $this->lockComplaint(
                $complaint
            );

            $this->ensureStatus(
                $complaint,
                $from
            );

            $complaint->status = $to;

            if (
                $to ===
                ComplaintStatus::CLOSED
            ) {
                $complaint->closed_at =
                    now();
            }

            $complaint->save();

            $this->log(
                complaint: $complaint,
                user: $user,
                status: $to,
                remarks: $remarks,
            );

            return $this->fresh($complaint);
        });
    }

    protected function lockComplaint(
        Complaint $complaint,
    ): Complaint {
        return Complaint::query()
            ->whereKey($complaint->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    protected function ensureStatus(
        Complaint $complaint,
        array $allowedStatuses,
    ): void {
        if (
            ! in_array(
                $complaint->status,
                $allowedStatuses,
                true
            )
        ) {
            $allowed = collect($allowedStatuses)
                ->map(
                    fn ($status) =>
                        $status->label()
                )
                ->join(', ');

            throw ValidationException::withMessages([
                'status' =>
                    "This complaint cannot be processed from its current status. " .
                    "Expected status: {$allowed}.",
            ]);
        }
    }

    protected function log(
        Complaint $complaint,
        User $user,
        ComplaintStatus $status,
        ?string $remarks = null,
    ): void {
        $complaint->logs()->create([
            'status' =>
                $status,

            'remarks' =>
                $remarks,

            'performed_by' =>
                $user->id,
        ]);
    }

    protected function fresh(
        Complaint $complaint,
    ): Complaint {
        return $complaint->fresh([
            'barangay',
            'resident',
            'submittedBy',
            'complaintType',
            'assignedTo',
            'logs.performedBy',
            'hearings.scheduledBy',
            'attachments.uploadedBy',
        ]);
    }
}