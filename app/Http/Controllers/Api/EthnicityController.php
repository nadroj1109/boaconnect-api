<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ethnicity\EthnicityIndexRequest;
use App\Http\Requests\Ethnicity\StoreEthnicityRequest;
use App\Http\Requests\Ethnicity\UpdateEthnicityRequest;
use App\Http\Resources\Ethnicity\EthnicityCollection;
use App\Http\Resources\Ethnicity\EthnicityResource;
use App\Models\Ethnicity;
use App\Services\EthnicityService;
use App\Traits\ApiResponse;

class EthnicityController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected EthnicityService $service
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(EthnicityIndexRequest $request)
    {
        $ethnicities = $this->service->paginate(

            $request->validated()

        );

        return $this->paginated(

            $ethnicities,

            new EthnicityCollection($ethnicities),

            'Ethnicities retrieved successfully.'

        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEthnicityRequest $request)
    {
        $ethnicity = $this->service->store(

            $request->validated()

        );

        return $this->success(

            new EthnicityResource($ethnicity),

            'Ethnicity created successfully.'

        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Ethnicity $ethnicity)
    {
        return $this->success(

            new EthnicityResource(

                $this->service->show($ethnicity)

            )

        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEthnicityRequest $request, Ethnicity $ethnicity)
    {
        $ethnicity = $this->service->update(

            $ethnicity,

            $request->validated()

        );

        return $this->success(

            new EthnicityResource($ethnicity),

            'Ethnicity updated successfully.'

        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Ethnicity $ethnicity)
    {
        $this->service->destroy($ethnicity);

        return $this->success(

            [],

            'Ethnicity deleted successfully.'

        );
    }
}
