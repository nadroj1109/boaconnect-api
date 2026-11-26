<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrenatalRecord\StorePrenatalRecordRequest;
use App\Http\Requests\PrenatalRecord\UpdatePrenatalRecordRequest;
use App\Http\Requests\PrenatalRecord\StorePrenatalVisitRequest;
use App\Http\Requests\PrenatalRecord\UpdatePrenatalVisitRequest;
use App\Http\Resources\PrenatalRecord\PrenatalRecordResource;
use App\Http\Resources\PrenatalRecord\PrenatalVisitResource;
use App\Services\PrenatalRecordService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class PrenatalRecordController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PrenatalRecordService $service
    ) {}

    public function index(Request $request)
    {
        $records = $this->service->paginate(
            $request->user(),
            $request->only([
                'search',
                'registration_date',
                'per_page',
            ])
        );

        return $this->paginated(
            $records,
            PrenatalRecordResource::collection($records),
            'Prenatal records retrieved successfully.'
        );
    }

    public function residents(Request $request)
    {
        $residents = $this->service->searchResidents(
            $request->user(),
            $request->only(['search', 'per_page'])
        );

        $data = $residents->getCollection()->map(function ($resident) {
            return [
                'id' => $resident->id,
                'full_name' => $resident->full_name,
                'date_of_birth' => $resident->date_of_birth?->format('F j, Y'),
                'age' => $resident->date_of_birth ? $resident->date_of_birth->age : null,
                'civil_status' => $resident->civil_status,
                'sitio_purok' => $resident->sitio_purok,
                'mobile_no' => $resident->mobile_no,
                'height' => $resident->height,
                'weight' => $resident->weight,
                'barangay_id' => $resident->barangay_id,
            ];
        });

        return $this->paginated(
            $residents,
            $data,
            'Residents retrieved successfully.'
        );
    }

    public function store(StorePrenatalRecordRequest $request)
    {
        $record = $this->service->store(
            $request->user(),
            $request->validated()
        );

        return $this->success(
            new PrenatalRecordResource($record),
            'Prenatal record created successfully.'
        );
    }

    public function show(Request $request, int $prenatalRecord)
    {
        $record = $this->service->find(
            $request->user(),
            $prenatalRecord
        );

        return $this->success(
            new PrenatalRecordResource($record),
            'Prenatal record retrieved successfully.'
        );
    }

    public function update(
        UpdatePrenatalRecordRequest $request,
        int $prenatalRecord
    ) {
        $record = $this->service->update(
            $request->user(),
            $prenatalRecord,
            $request->validated()
        );

        return $this->success(
            new PrenatalRecordResource($record),
            'Prenatal record updated successfully.'
        );
    }

    public function destroy(Request $request, int $prenatalRecord)
    {
        $this->service->delete(
            $request->user(),
            $prenatalRecord
        );

        return $this->success(
            null,
            'Prenatal record deleted successfully.'
        );
    }

    public function storeVisit(
        StorePrenatalVisitRequest $request,
        int $prenatalRecord
    ) {
        $visit = $this->service->storeVisit(
            $request->user(),
            $prenatalRecord,
            $request->validated()
        );

        return $this->success(
            new PrenatalVisitResource($visit),
            'Prenatal visit created successfully.'
        );
    }

    public function updateVisit(
        UpdatePrenatalVisitRequest $request,
        int $prenatalRecord,
        int $visit
    ) {
        $visit = $this->service->updateVisit(
            $request->user(),
            $prenatalRecord,
            $visit,
            $request->validated()
        );

        return $this->success(
            new PrenatalVisitResource($visit),
            'Prenatal visit updated successfully.'
        );
    }

    public function destroyVisit(
        Request $request,
        int $prenatalRecord,
        int $visit
    ) {
        $this->service->deleteVisit(
            $request->user(),
            $prenatalRecord,
            $visit
        );

        return $this->success(
            null,
            'Prenatal visit deleted successfully.'
        );
    }
}
