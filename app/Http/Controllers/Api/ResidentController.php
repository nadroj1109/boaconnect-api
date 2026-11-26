<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Resident\ResidentIndexRequest;
use App\Http\Requests\Resident\StoreResidentRequest;
use App\Http\Requests\Resident\UpdateResidentRequest;
use App\Http\Requests\Resident\ResidentRegistrationIndexRequest;
use App\Http\Requests\Resident\UpdateResidentHouseholdRequest;
use App\Http\Requests\Resident\RejectResidentRegistrationRequest;
use App\Http\Resources\Resident\ResidentCollection;
use App\Http\Resources\Resident\ResidentResource;
use App\Models\Resident;
use App\Notifications\ResidentRegistrationApproved;
use App\Services\ResidentService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ResidentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ResidentService $service
    ) {}

    public function index(ResidentIndexRequest $request)
    {
        $residents = $this->service->paginate(

            $request->validated()

        );

        return $this->paginated(

            $residents,

            new ResidentCollection($residents),

            'Residents retrieved successfully.'

        );
    }

    public function store(StoreResidentRequest $request)
    {
        $user = $this->service->store(

            $request->validated()

        );

        return $this->success(

            new ResidentResource($user),

            'Resident created successfully.'

        );
    }

    public function show(Resident $resident)
    {
        return $this->success(

            new ResidentResource(

                $this->service->show($resident)

            )

        );
    }

    public function update(UpdateResidentRequest $request, Resident $resident)
    {
        $resident = $this->service->update(

            $resident,

            $request->validated()

        );

        return $this->success(

            new ResidentResource($resident),

            'Resident updated successfully.'

        );
    }

    public function availableResidentsForUser(Request $request)
    {
        $request->validate([

            'barangay_id' => [
                'required',
                'integer',
                'exists:barangays,id',
            ],

        ]);


        return $this->success(
            $this->service->availableResidentsForUser(
                $request->integer(
                    'barangay_id'
                )
            ),

            'Residents retrieved successfully.'
        );
    }

    public function residentRegistrations(ResidentRegistrationIndexRequest $request)
    {
        $residents = $this->service->residentRegistrations(
            $request->validated()
        );

        return $this->paginated(

            $residents,

            new ResidentCollection($residents),

            'Residents retrieved successfully.'

        );
    }

    public function updateResidentHousehold(UpdateResidentHouseholdRequest $request, Resident $resident)
    {
        $resident = $this->service->updateHouseholdResident(

            $resident,

            $request->validated()

        );

         /*
        |--------------------------------------------------------------------------
        | Send Approval Email
        |--------------------------------------------------------------------------
        */

        if (!empty($resident->email_address)) {
            try {
                Notification::route(
                    'mail',
                    $resident->email_address
                )->notify(
                    new ResidentRegistrationApproved($resident)
                );
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $this->success(

            new ResidentResource($resident),

            'Resident updated successfully.'

        );
    }

    public function rejectResidentRegistration(
        RejectResidentRegistrationRequest $request,
        Resident $resident
    ) {
        $resident = $this->service->rejectHouseholdResident(
            $resident,
            $request->validated()['rejection_reason']
        );

        return $this->success(
            new ResidentResource($resident),
            'Resident registration rejected successfully.'
        );
    }

}
