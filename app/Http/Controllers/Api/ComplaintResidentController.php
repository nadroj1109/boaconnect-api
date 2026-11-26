<?php

namespace App\Http\Controllers\Api;

use App\Enums\ComplaintSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Complaint\StoreResidentComplaintRequest;
use App\Http\Resources\ComplaintResource;
use App\Http\Resources\ResidentComplaintHistoryResource;
use App\Models\Complaint;
use App\Models\ComplaintType;
use App\Models\Resident;
use App\Services\Complaint\ComplaintAuthorizationService;
use App\Services\Complaint\ComplaintService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ComplaintResidentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ComplaintService $complaintService,
        protected ComplaintAuthorizationService $authorization,
    ) {
    }

    public function store(
        StoreResidentComplaintRequest $request,
    ) {
        $user = $request->user();

        $this->authorization
            ->authorizeCreateResidentComplaint(
                $user
            );

        $resident = $user->resident;

        $complaint = $this->complaintService->create(
            resident: $resident,
            submittedBy: $user,
            source: ComplaintSource::RESIDENT,
            data: $request->validated(),
        );

        return $this->success(
            new ComplaintResource($complaint),
            'Complaint submitted successfully.'
        );
    }

    public function complaintTypes()
    {
        $types = ComplaintType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'description',
            ]);

        return $this->success(
            $types,
            'Complaint types retrieved successfully.'
        );
    }

    public function respondents(
        Request $request
    ) {
        $user = $request->user();

        $resident = $user->resident;

        if (! $resident) {
            abort(
                403,
                'Your account is not linked to a resident record.'
            );
        }

        $residents = Resident::query()
            ->where(
                'barangay_id',
                $resident->barangay_id
            )
            ->where(
                'id',
                '!=',
                $resident->id
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return $this->success(
            $residents->map(
                fn ($resident) => [
                    'value' => $resident->id,
                    'label' => $resident->full_name,
                ]
            )->values(),
            'Respondents retrieved successfully.'
        );
    }

    public function myComplaintHistory(
        Request $request
    ) {
        $user = $request->user();

        if ($user->resident_id === null) {
            abort(
                403,
                'Your account is not linked to a resident.'
            );
        }

        $complaints = Complaint::query()
            ->with([
                'complaintType',
            ])
            ->where(
                'respondent_id',
                $user->resident_id
            )
            ->latest('created_at')
            ->get();

        return $this->success(
            ResidentComplaintHistoryResource::collection(
                $complaints
            ),
            'Your complaint history retrieved successfully.'
        );
    }
}
