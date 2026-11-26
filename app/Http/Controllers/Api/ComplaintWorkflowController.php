<?php

namespace App\Http\Controllers\Api;

use App\Enums\ComplaintStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Complaint\AssignComplaintRequest;
use App\Http\Requests\Complaint\ComplaintWorkflowRemarksRequest;
use App\Http\Requests\Complaint\ResolveComplaintRequest;
use App\Http\Requests\Complaint\ReviewComplaintRequest;
use App\Http\Requests\Complaint\ScheduleComplaintHearingRequest;
use App\Http\Resources\ComplaintResource;
use App\Models\Complaint;
use App\Models\User;
use App\Services\Complaint\ComplaintAuthorizationService;
use App\Services\Complaint\ComplaintWorkflowService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ComplaintWorkflowController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ComplaintWorkflowService $workflow,
        protected ComplaintAuthorizationService $authorization,
    ) {
    }

    public function review(
        ReviewComplaintRequest $request,
        Complaint $complaint,
    ) {
        $user = $request->user();

        $this->authorization->authorizeReview(
            $user,
            $complaint,
        );

        $complaint = $this->workflow->markUnderReview(
            complaint: $complaint,
            user: $user,
            remarks: $request->input('remarks'),
        );

        return $this->success(
            new ComplaintResource($complaint),
            'Complaint marked as under review.'
        );
    }

    public function assign(
        AssignComplaintRequest $request,
        Complaint $complaint,
    ) {
        $user = $request->user();

        $this->authorization->authorizeAssign(
            $user,
            $complaint,
        );

        $assignee = User::query()
            ->whereKey(
                $request->integer('assigned_to')
            )
            ->where(
                'barangay_id',
                $complaint->barangay_id
            )
            ->firstOrFail();

        $complaint = $this->workflow->assign(
            complaint: $complaint,
            user: $user,
            assignee: $assignee,
            remarks: $request->input('remarks'),
        );

        return $this->success(
            new ComplaintResource($complaint),
            'Complaint assigned successfully.'
        );
    }

    public function schedule(
        ScheduleComplaintHearingRequest $request,
        Complaint $complaint,
    ) {
        $user = $request->user();

        $this->authorization->authorizeManageHearing(
            $user,
            $complaint,
        );

        $complaint = $this->workflow->schedule(
            complaint: $complaint,
            user: $request->user(),
            hearingDate:
                $request->input('hearing_date'),
            hearingTime:
                $request->input('hearing_time'),
            location:
                $request->input('location'),
            remarks:
                $request->input('remarks'),
        );

        return $this->success(
            new ComplaintResource($complaint),
            'Complaint hearing scheduled successfully.'
        );
    }

    public function hearing(
        ComplaintWorkflowRemarksRequest $request,
        Complaint $complaint,
    ) {
        $user = $request->user();

        $this->authorization->authorizeManageHearing(
            $user,
            $complaint,
        );

        $complaint = $this->workflow->startHearing(
            complaint: $complaint,
            user: $request->user(),
            remarks: $request->input('remarks'),
        );

        return $this->success(
            new ComplaintResource($complaint),
            'Complaint hearing started.'
        );
    }

    public function resolve(
        ResolveComplaintRequest $request,
        Complaint $complaint,
    ) {
        $user = $request->user();

        $this->authorization->authorizeResolve(
            $user,
            $complaint,
        );

        $complaint = $this->workflow->resolve(
            complaint: $complaint,
            user: $request->user(),
            resolution:
                $request->input('resolution'),
            remarks:
                $request->input('remarks'),
        );

        return $this->success(
            new ComplaintResource($complaint),
            'Complaint resolved successfully.'
        );
    }

    public function close(
        ComplaintWorkflowRemarksRequest $request,
        Complaint $complaint,
    ) {
        $user = $request->user();

        $this->authorization->authorizeClose(
            $user,
            $complaint,
        );

        $complaint = $this->workflow->close(
            complaint: $complaint,
            user: $request->user(),
            remarks: $request->input('remarks'),
        );

        return $this->success(
            new ComplaintResource($complaint),
            'Complaint closed successfully.'
        );
    }

    public function reject(
        ComplaintWorkflowRemarksRequest $request,
        Complaint $complaint,
    ) {
        $user = $request->user();

        $this->authorization->authorizeReject(
            $user,
            $complaint,
        );

        $complaint = $this->workflow->reject(
            complaint: $complaint,
            user: $request->user(),
            remarks: $request->input('remarks'),
        );

        return $this->success(
            new ComplaintResource($complaint),
            'Complaint rejected successfully.'
        );
    }

    public function cancel(
        ComplaintWorkflowRemarksRequest $request,
        Complaint $complaint,
    ) {
        $user = $request->user();

        $this->authorization->authorizeCancel(
            $user,
            $complaint,
        );

        $complaint = $this->workflow->cancel(
            complaint: $complaint,
            user: $request->user(),
            remarks: $request->input('remarks'),
        );

        return $this->success(
            new ComplaintResource($complaint),
            'Complaint cancelled successfully.'
        );
    }
}
