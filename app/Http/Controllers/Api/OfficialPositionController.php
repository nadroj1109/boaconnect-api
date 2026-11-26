<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OfficialPosition\OfficialPositionIndexRequest;
use App\Http\Requests\OfficialPosition\StoreOfficialPositionRequest;
use App\Http\Requests\OfficialPosition\UpdateOfficialPositionRequest;
use App\Http\Resources\OfficialPositionResource;
use App\Models\OfficialPosition;
use App\Services\OfficialPositionService;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class OfficialPositionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected OfficialPositionService $service
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(OfficialPositionIndexRequest $request)
    {
        $officialPositions = $this->service->paginate(

            $request->validated()

        );

        return $this->paginated(

            $officialPositions,

            OfficialPositionResource::collection($officialPositions),

            'Official Position retrieved successfully.'

        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOfficialPositionRequest $request)
    {
        $officialPosition =
            $this->service->store(
                $request->validated()
            );

        return $this->success(
            new OfficialPositionResource(
                $officialPosition
            ),

            'Official Position created successfully.'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(OfficialPosition $officialPosition)
    {
        return $this->success(

            new OfficialPositionResource(

                $this->service->show(
                    $officialPosition
                )

            )

        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOfficialPositionRequest $request, OfficialPosition $officialPosition)
    {
        $officialPosition =
            $this->service->update(

                $officialPosition,

                $request->validated()

            );

        return $this->success(

            new OfficialPositionResource(
                $officialPosition
            ),

            'Official Position updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(OfficialPosition $officialPosition)
    {
        $this->service->destroy(
            $officialPosition
        );

        return $this->success(
            [],

            'Official Position deleted successfully.'
        );
    }
}
