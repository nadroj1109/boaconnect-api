<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SitioPurok\SitioPurokIndexRequest;
use App\Http\Requests\SitioPurok\StoreSitioPurokRequest;
use App\Http\Requests\SitioPurok\UpdateSitioPurokRequest;
use App\Http\Resources\SitioPurokResource;
use App\Models\SitioPurok;
use App\Services\SitioPurokService;
use App\Traits\ApiResponse;

class SitioPurokController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SitioPurokService $service
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(SitioPurokIndexRequest $request)
    {
        $sitioPuroks = $this->service->paginate(

            $request->validated()

        );

        return $this->paginated(

            $sitioPuroks,

            SitioPurokResource::collection($sitioPuroks),

            'Sitio/Puroks retrieved successfully.'

        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSitioPurokRequest $request)
    {
        $sitioPurok =
            $this->service->store(
                $request->validated()
            );

        return $this->success(
            new SitioPurokResource(
                $sitioPurok
            ),

            'Sitio/Purok created successfully.'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(SitioPurok $sitioPurok)
    {
        return $this->success(

            new SitioPurokResource(

                $this->service->show(
                    $sitioPurok
                )

            )

        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSitioPurokRequest $request, SitioPurok $sitioPurok)
    {
        $sitioPurok =
            $this->service->update(

                $sitioPurok,

                $request->validated()

            );

        return $this->success(

            new SitioPurokResource(
                $sitioPurok
            ),

            'Sitio/Purok updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SitioPurok $sitioPurok)
    {
        $this->service->destroy(
            $sitioPurok
        );

        return $this->success(
            [],

            'Sitio/Purok deleted successfully.'
        );
    }

    public function available()
    {
        return $this->success(

            SitioPurokResource::collection(
                $this->service->available()
            ),

            'Available sitio/purok retrieved successfully.'
        );
    }
}
