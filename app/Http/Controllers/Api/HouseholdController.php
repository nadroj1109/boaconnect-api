<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Household\HouseholdIndexRequest;
use App\Http\Requests\Household\StoreHouseholdRequest;
use App\Http\Requests\Household\UpdateHouseholdRequest;
use App\Http\Resources\Household\HouseholdCollection;
use App\Http\Resources\Household\HouseholdResource;
use App\Http\Resources\Household\HouseholdMemberResource;
use App\Models\Household;
use App\Services\HouseholdService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HouseholdController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected HouseholdService $service
    ) {}

    public function index(HouseholdIndexRequest $request)
    {
        $households = $this->service->paginate(

            $request->validated()

        );

        return $this->paginated(

            $households,

            new HouseholdCollection($households),

            'Households retrieved successfully.'

        );
    }

    public function store(StoreHouseholdRequest $request)
    {
        $user = $this->service->store(

            $request->validated()

        );

        return $this->success(

            new HouseholdResource($user),

            'Household created successfully.'

        );
    }

    public function show(Household $household)
    {
        return $this->success(

            new HouseholdResource(

                $this->service->show($household)

            )

        );
    }

    public function update(UpdateHouseholdRequest $request, Household $household)
    {
        $household = $this->service->update(

            $household,

            $request->validated()

        );

        return $this->success(

            new HouseholdResource($household),

            'Household updated successfully.'

        );
    }

    public function members(Request $request)
    {
        $resident = $request->user()->resident;

        abort_if(
            ! $resident,
            403,
            'Resident account is required.'
        );

        $members = $resident->household
            ->residents()
            ->orderBy('first_name')
            ->get();

        return HouseholdMemberResource::collection(
            $members
        );
    }

    public function householdNumbers()
    {
        $households = Household::where('barangay_id', Auth::user()->barangay_id)
            ->orderBy('household_no')
            ->get()
            ->map(fn ($household) => [
                'value' => $household->id,
                'name' => $household->household_no
            ]);

        return response()->json($households);
    }
}
