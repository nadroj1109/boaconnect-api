<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BarangayOfficial\BarangayOfficialIndexRequest;
use App\Http\Requests\BarangayOfficial\StoreBarangayOfficialRequest;
use App\Http\Requests\BarangayOfficial\UpdateBarangayOfficialRequest;
use App\Http\Resources\BarangayOfficialResource;
use App\Http\Resources\ResidentResource;
use App\Models\OfficialPosition;
use App\Models\Resident;
use App\Models\BarangayOfficial;
use App\Services\BarangayOfficialService;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class BarangayOfficialController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected BarangayOfficialService $service
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(
        BarangayOfficialIndexRequest $request
    ) {
        $officials =
            $this->service->paginate(
                $request->validated()
            );

        return $this->paginated(

            $officials,

            BarangayOfficialResource::collection(
                $officials
            ),

            'Barangay Officials retrieved successfully.'

        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        StoreBarangayOfficialRequest $request
    ) {
        $official =
            $this->service->store(
                $request->validated()
            );

        return $this->success(

            new BarangayOfficialResource(
                $this->service->show(
                    $official
                )
            ),

            'Barangay Official created successfully.'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(
        BarangayOfficial $barangayOfficial
    ) {
        return $this->success(

            new BarangayOfficialResource(

                $this->service->show(
                    $barangayOfficial
                )

            )

        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateBarangayOfficialRequest $request,
        BarangayOfficial $barangayOfficial
    ) {
        $official =
            $this->service->update(

                $barangayOfficial,

                $request->validated()

            );

        return $this->success(

            new BarangayOfficialResource(
                $official
            ),

            'Barangay Official updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(
        BarangayOfficial $barangayOfficial
    ) {
        $this->service->destroy(
            $barangayOfficial
        );

        return $this->success(
            [],
            'Barangay Official deleted successfully.'
        );
    }

    public function residents(
        Request $request
    ) {

        $request->validate([

            'barangay_id' => [
                'required',
                'integer',
                'exists:barangays,id',
            ],

        ]);


        return $this->success(
            $this->service->residents(
                $request->integer(
                    'barangay_id'
                )
            ),

            'Residents retrieved successfully.'
        );

    }

    public function assignable(
        Request $request
    ) {

        $request->validate([

            'barangay_id' => [
                'required',
                'integer',
                'exists:barangays,id',
            ],

        ]);


        $officials =
            $this->service->assignable(

                $request->integer(
                    'barangay_id'
                )

            );


        return $this->success(

            $officials->map(
                fn ($official) => [

                    'value' =>
                        $official
                            ->resident
                            ?->user
                            ?->id,

                    'name' =>
                        $official
                            ->resident
                            ?->full_name,

                    'position' =>
                        $official
                            ->position
                            ?->name,

                ]
            )
                ->filter(
                    fn ($official) =>
                        $official['value'] !== null
                )
                ->values(),

            'Assignable barangay officials retrieved successfully.'

        );

    }

    public function availableForUser(Request $request)
    {
        $validated = $request->validate([
            'barangay_id' => [
                'required',
                'integer',
                'exists:barangays,id',
            ],
            'user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
        ]);

        $officials = $this->service->availableForUser(
            (int) $validated['barangay_id'],
            isset($validated['user_id'])
                ? (int) $validated['user_id']
                : null
        );

        return $this->success(
            $officials->map(
                fn ($official) => [
                    'value' => $official->id,
                    'name' => $official->resident?->full_name,
                    'position' => $official->position?->name,
                    'email' => $official->resident?->email_address,
                    'mobile_number' => $official->resident?->mobile_no,
                ]
            )->values(),
            'Available barangay officials retrieved successfully.'
        );
    }
}
