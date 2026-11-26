<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Barangay\BarangayIndexRequest;
use App\Http\Requests\Barangay\StoreBarangayRequest;
use App\Http\Requests\Barangay\UpdateBarangayRequest;
use App\Http\Resources\Barangay\BarangayCollection;
use App\Http\Resources\Barangay\BarangayResource;
use Illuminate\Http\Request;
use App\Models\Barangay;
use App\Services\BarangayService;
use App\Traits\ApiResponse;

class BarangayController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected BarangayService $service
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(BarangayIndexRequest $request)
    {
        $barangays = $this->service->paginate(

            $request->validated()

        );

        return $this->paginated(

            $barangays,

            new BarangayCollection($barangays),

            'Barangays retrieved successfully.'

        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBarangayRequest $request)
    {
        $barangay = $this->service->store(

            $request->validated()

        );

        return $this->success(

            new BarangayResource($barangay),

            'Barangay created successfully.'

        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Barangay $barangay)
    {
        return $this->success(

            new BarangayResource(

                $this->service->show($barangay)

            )

        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBarangayRequest $request, Barangay $barangay)
    {
        $barangay = $this->service->update(

            $barangay,

            $request->validated()

        );

        return $this->success(

            new BarangayResource($barangay),

            'Barangay updated successfully.'

        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Barangay $barangay)
    {
        $this->service->destroy($barangay);

        return $this->success(

            [],

            'Barangay deleted successfully.'

        );
    }

    public function options()
    {
        $barangays = $this->service->options();

        return $this->success(
            $barangays,
            'Barangay options retrieved successfully.'
        );
    }
}
