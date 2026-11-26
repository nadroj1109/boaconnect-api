<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Complaint\StoreComplaintRequest;
use App\Http\Requests\Complaint\UpdateComplaintRequest;
use App\Http\Requests\Complaint\StoreHistoricalComplaintRequest;
use App\Http\Resources\ComplaintResource;
use App\Http\Resources\ResidentComplaintHistoryResource;
use App\Models\Complaint;
use App\Models\Resident;
use App\Models\User;
use App\Services\Complaint\ComplaintAuthorizationService;
use App\Services\Complaint\ComplaintService;
use App\Enums\ComplaintSource;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ComplaintService $complaintService,
        protected ComplaintAuthorizationService $authorization,
    ) {
    }

    /**
     * Display a listing of complaints.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Complaint::query()
            ->with([
                'resident',
                'respondent',
                'submittedBy',
                'complaintType',
                'assignedTo',
            ])
            ->latest('created_at');

        if ($user->resident_id !== null) {
            $query->where(
                'resident_id',
                $user->resident_id
            );
        } elseif ($user->barangay_id !== null) {
            $query->where(
                'barangay_id',
                $user->barangay_id
            );
        } elseif (
            ! $user->hasRole('Super Administrator')
        ) {
            $query->whereRaw('1 = 0');
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')
            );
        }

        if ($request->filled('complaint_type_id')) {
            $query->where(
                'complaint_type_id',
                $request->integer('complaint_type_id')
            );
        }

        if ($request->filled('resident_id')) {
            $query->where(
                'resident_id',
                $request->integer('resident_id')
            );
        }

        if ($request->filled('search')) {
            $search = $request->string('search');

            $query->where(function ($query) use ($search) {
                $query
                    ->where(
                        'complaint_no',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'subject',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        $perPage = min(
            $request->integer('per_page', 15),
            100
        );

        $complaints = $query->paginate($perPage);

        return $this->paginated(
            $complaints,
            ComplaintResource::collection(
                $complaints->getCollection()
            ),
            'Complaints retrieved successfully.'
        );
    }

    /**
     * Store a complaint manually by staff.
     */
    public function store(
        StoreComplaintRequest $request,
    ) {
        $user = $request->user();

        $this->authorization
            ->authorizeCreateStaffComplaint($user);

        $resident = $this->complaintService
            ->findResidentForUser(
                $user,
                $request->integer('resident_id')
            );

        $complaint = $this->complaintService->create(
            resident: $resident,
            submittedBy: $user,
            source: ComplaintSource::STAFF,
            data: $request->validated(),
        );

        return $this->success(
            new ComplaintResource($complaint),
            'Complaint created successfully.'
        );
    }

    /**
     * Display the specified complaint.
     */
    public function show(
        Request $request,
        Complaint $complaint,
    ) {
        $user = $request->user();

        $this->authorization->authorizeView(
            $user,
            $complaint,
        );

        $complaint->load([
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

        return $this->success(
            new ComplaintResource($complaint),
            'Complaint retrieved successfully.'
        );
    }

    /**
     * Update the complaint information.
     */
    public function update(
        UpdateComplaintRequest $request,
        Complaint $complaint,
    ) {
        $user = $request->user();

        $this->authorization->authorizeUpdate(
            $user,
            $complaint,
        );

        $complaint = $this->complaintService->update(
            $complaint,
            $request->validated(),
        );

        return $this->success(
            new ComplaintResource($complaint),
            'Complaint updated successfully.'
        );
    }

    /**
     * Delete the complaint.
     */
    public function destroy(
        Request $request,
        Complaint $complaint,
    ) {
        $user = $request->user();

        $this->authorization->authorizeDelete(
            $user,
            $complaint,
        );

        $complaint->delete();

        return $this->success(
            null,
            'Complaint deleted successfully.'
        );
    }

    public function storeHistorical(
        StoreHistoricalComplaintRequest $request,
    ) {
        $user = $request->user();

        $this->authorization
            ->authorizeCreateStaffComplaint($user);

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

    public function residents()
    {
        $user = request()->user();

        $query = Resident::query();

        /*
         * Resident user:
         * only their own resident record.
         */
        if ($user->resident_id !== null) {

            $query->where(
                'id',
                $user->resident_id
            );

        /*
         * Barangay user:
         * only residents belonging to
         * the user's barangay.
         */
        } elseif ($user->barangay_id !== null) {

            $query->whereHas(
                'household',
                function ($household) use ($user) {

                    $household->where(
                        'barangay_id',
                        $user->barangay_id
                    );

                }
            );

        /*
         * Super Administrator:
         * no additional barangay restriction.
         */
        } elseif (
            ! $user->hasRole('Super Administrator')
        ) {

            $query->whereRaw('1 = 0');

        }

        $residents = $query
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

            'Residents retrieved successfully.'

        );
    }

    public function respondentHistory(
        Request $request,
        Resident $resident,
    ) {
        $user = $request->user();

        $this->authorization
            ->authorizeViewRespondentHistory(
                $user,
                $resident
            );

        $complaints =
            $this->complaintService
                ->respondentHistory(
                    $resident
                );

        return $this->success(
            ResidentComplaintHistoryResource::collection(
                $complaints
            ),
            'Resident complaint history retrieved successfully.'
        );
    }
}
