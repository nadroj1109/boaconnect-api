<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Complaint\CancelComplaintHearingRequest;
use App\Http\Requests\Complaint\CompleteComplaintHearingRequest;
use App\Http\Requests\Complaint\RescheduleComplaintHearingRequest;
use App\Http\Requests\Complaint\StoreHistoricalComplaintHearingRequest;
use App\Http\Resources\ComplaintResource;
use App\Models\Complaint;
use App\Models\ComplaintHearing;
use App\Services\Complaint\ComplaintAuthorizationService;
use App\Services\Complaint\ComplaintHearingService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ComplaintHearingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ComplaintHearingService $hearingService,
        protected ComplaintAuthorizationService $authorization,
    ) {
    }

    public function complete(
        CompleteComplaintHearingRequest $request,
        Complaint $complaint,
        ComplaintHearing $hearing,
    ) {
        $user = $request->user();

        $this->authorization->authorizeManageHearing(
            $user,
            $complaint,
        );

        $this->ensureHearingBelongsToComplaint(
            $complaint,
            $hearing,
        );

        $this->hearingService->complete(
            hearing: $hearing,
            user: $user,
            remarks: $request->input('remarks'),
        );

        $complaint->load([
            'barangay',
            'resident',
            'submittedBy',
            'complaintType',
            'assignedTo',
            'logs.performedBy',
            'hearings.scheduledBy',
            'attachments.uploadedBy',
        ]);

        return $this->success(
            new ComplaintResource($complaint),
            'Complaint hearing completed successfully.'
        );
    }

    public function cancel(
        CancelComplaintHearingRequest $request,
        Complaint $complaint,
        ComplaintHearing $hearing,
    ) {
        $user = $request->user();

        $this->authorization->authorizeManageHearing(
            $user,
            $complaint,
        );

        $this->ensureHearingBelongsToComplaint(
            $complaint,
            $hearing,
        );

        $this->hearingService->cancel(
            hearing: $hearing,
            user: $user,
            remarks: $request->input('remarks'),
        );

        $complaint->load([
            'barangay',
            'resident',
            'submittedBy',
            'complaintType',
            'assignedTo',
            'logs.performedBy',
            'hearings.scheduledBy',
            'attachments.uploadedBy',
        ]);

        return $this->success(
            new ComplaintResource($complaint),
            'Complaint hearing cancelled successfully.'
        );
    }

    public function reschedule(
        RescheduleComplaintHearingRequest $request,
        Complaint $complaint,
        ComplaintHearing $hearing,
    ) {
        $user = $request->user();

        $this->authorization->authorizeManageHearing(
            $user,
            $complaint,
        );

        $this->ensureHearingBelongsToComplaint(
            $complaint,
            $hearing,
        );

        $this->hearingService->reschedule(
            hearing: $hearing,
            user: $user,
            hearingDate: $request->input('hearing_date'),
            hearingTime: $request->input('hearing_time'),
            location: $request->input('location'),
            remarks: $request->input('remarks'),
        );

        $complaint->load([
            'barangay',
            'resident',
            'submittedBy',
            'complaintType',
            'assignedTo',
            'logs.performedBy',
            'hearings.scheduledBy',
            'attachments.uploadedBy',
        ]);

        return $this->success(
            new ComplaintResource($complaint),
            'Complaint hearing rescheduled successfully.'
        );
    }

    public function storeHistorical(
        StoreHistoricalComplaintRequest $request,
    ) {
        $user = $request->user();

        $this->authorization
            ->authorizeEncodeHistorical($user);

        $resident = $this->complaintService
            ->findResidentForUser(
                $user,
                $request->integer('resident_id')
            );

        $complaint =
            $this->complaintService
                ->createHistorical(
                    resident: $resident,
                    submittedBy: $user,
                    data: $request->validated(),
                );

        return $this->success(
            new ComplaintResource($complaint),
            'Historical complaint record encoded successfully.'
        );
    }

    protected function ensureHearingBelongsToComplaint(
        Complaint $complaint,
        ComplaintHearing $hearing,
    ): void {
        if (
            $hearing->complaint_id !==
            $complaint->id
        ) {
            abort(
                404,
                'Complaint hearing not found.'
            );
        }
    }
}
