<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChildNutrition\StoreChildNutritionAssessmentRequest;
use App\Http\Requests\ChildNutrition\StoreChildNutritionMonitoringRequest;
use App\Http\Requests\ChildNutrition\UpdateChildNutritionAssessmentRequest;
use App\Http\Requests\ChildNutrition\UpdateChildNutritionMonitoringRequest;
use App\Http\Resources\ChildNutrition\ChildNutritionAssessmentResource;
use App\Http\Resources\ChildNutrition\ChildNutritionMonitoringResource;
use App\Services\ChildNutritionMonitoringService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ChildNutritionMonitoringController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ChildNutritionMonitoringService $childNutritionMonitoringService
    ) {}

    /**
     * Display a paginated list of child nutrition monitoring records.
     */
    public function index(Request $request)
    {
        $records = $this->childNutritionMonitoringService->paginate(
            $request->user(),
            $request->only([
                'monitoring_year',
                'search',
                'per_page',
            ])
        );

        return $this->paginated(
            $records,
            ChildNutritionMonitoringResource::collection($records),
            'Child nutrition monitoring records retrieved successfully.'
        );
    }

    /**
     * Retrieve all records for the annual printable report.
     */
    public function report(Request $request)
    {
        $validated = $request->validate([
            'monitoring_year' => ['required', 'integer', 'between:1900,2100'],
        ]);

        $records = $this->childNutritionMonitoringService->getReportRecords(
            $request->user(),
            (int) $validated['monitoring_year']
        );

        return $this->success(
            ChildNutritionMonitoringResource::collection($records),
            'Child nutrition monitoring report retrieved successfully.'
        );
    }

    /**
     * Store a new annual child nutrition monitoring record.
     */
    public function store(StoreChildNutritionMonitoringRequest $request)
    {
        $record = $this->childNutritionMonitoringService->store(
            $request->user(),
            $request->validated()
        );

        return $this->success(
            new ChildNutritionMonitoringResource($record->load([
                'resident',
                'barangay',
                'creator',
            ])),
            'Child nutrition monitoring record created successfully.'
        );
    }

    /**
     * Display a specific monitoring record and its assessments.
     */
    public function show(Request $request, int $id)
    {
        $record = $this->childNutritionMonitoringService->find(
            $request->user(),
            $id
        );

        return $this->success(
            new ChildNutritionMonitoringResource($record),
            'Child nutrition monitoring record retrieved successfully.'
        );
    }

    /**
     * Update a child nutrition monitoring record.
     */
    public function update(
        UpdateChildNutritionMonitoringRequest $request,
        int $id
    ) {
        $record = $this->childNutritionMonitoringService->update(
            $request->user(),
            $id,
            $request->validated()
        );

        return $this->success(
            new ChildNutritionMonitoringResource($record->load([
                'resident',
                'barangay',
                'creator',
                'updater',
            ])),
            'Child nutrition monitoring record updated successfully.'
        );
    }

    /**
     * Delete a child nutrition monitoring record.
     */
    public function destroy(Request $request, int $id)
    {
        $this->childNutritionMonitoringService->delete(
            $request->user(),
            $id
        );

        return $this->success(
            null,
            'Child nutrition monitoring record deleted successfully.'
        );
    }

    /**
     * Search eligible residents who can be registered for monitoring.
     */
    public function residents(Request $request)
    {
        $residents = $this->childNutritionMonitoringService->searchResidents(
            $request->user(),
            $request->only(['search', 'per_page', 'monitoring_year'])
        );

        $data = $residents->getCollection()->map(function ($resident) {
            return [
                'id' => $resident->id,
                'full_name' => $resident->full_name,
                'date_of_birth' => $resident->date_of_birth?->format('F j, Y'),
                'age' => $resident->date_of_birth
                    ? $resident->date_of_birth->age
                    : null,
                'sex' => $resident->sex,
                'sitio_purok' => $resident->sitio_purok,
                'mobile_no' => $resident->mobile_no,
                'barangay_id' => $resident->barangay_id,
            ];
        });

        return $this->paginated(
            $residents,
            $data,
            'Residents retrieved successfully.'
        );
    }

    /**
     * Store a monthly assessment.
     */
    public function storeAssessment(
        StoreChildNutritionAssessmentRequest $request,
        int $id
    ) {
        $assessment = $this->childNutritionMonitoringService->storeAssessment(
            $request->user(),
            $id,
            $request->validated()
        );

        return $this->success(
            new ChildNutritionAssessmentResource($assessment->load('creator')),
            'Monthly assessment created successfully.'
        );
    }

    /**
     * Update a monthly assessment.
     */
    public function updateAssessment(
        UpdateChildNutritionAssessmentRequest $request,
        int $id,
        int $assessmentId
    ) {
        $assessment = $this->childNutritionMonitoringService->updateAssessment(
            $request->user(),
            $id,
            $assessmentId,
            $request->validated()
        );

        return $this->success(
            new ChildNutritionAssessmentResource($assessment->load([
                'creator',
                'updater',
            ])),
            'Monthly assessment updated successfully.'
        );
    }

    /**
     * Delete a monthly assessment.
     */
    public function destroyAssessment(
        Request $request,
        int $id,
        int $assessmentId
    ) {
        $this->childNutritionMonitoringService->deleteAssessment(
            $request->user(),
            $id,
            $assessmentId
        );

        return $this->success(
            null,
            'Monthly assessment deleted successfully.'
        );
    }
}
