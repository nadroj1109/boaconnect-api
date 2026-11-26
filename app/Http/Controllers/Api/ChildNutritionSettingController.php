<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChildNutritionMonitoringService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ChildNutritionSettingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ChildNutritionMonitoringService $childNutritionMonitoringService
    ) {}

    /**
     * Display child nutrition monitoring settings.
     */
    public function show()
    {
        $settings = $this->childNutritionMonitoringService->getSettings();

        return $this->success([
            'age_restriction_enabled' => $settings->age_restriction_enabled,
            'minimum_age_months' => $settings->minimum_age_months,
            'maximum_age_months' => $settings->maximum_age_months,
        ], 'Child nutrition monitoring settings retrieved successfully.');
    }

    /**
     * Update child nutrition monitoring settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'age_restriction_enabled' => [
                'required',
                'boolean',
            ],

            'minimum_age_months' => [
                'nullable',
                'required_if:age_restriction_enabled,1',
                'integer',
                'min:0',
                'max:1200',
            ],

            'maximum_age_months' => [
                'nullable',
                'required_if:age_restriction_enabled,1',
                'integer',
                'min:0',
                'max:1200',
                'gte:minimum_age_months',
            ],
        ]);

        $settings = $this->childNutritionMonitoringService->updateSettings(
            $request->user(),
            $validated
        );

        return $this->success([
            'age_restriction_enabled' => $settings->age_restriction_enabled,
            'minimum_age_months' => $settings->minimum_age_months,
            'maximum_age_months' => $settings->maximum_age_months,
        ], 'Child nutrition monitoring settings updated successfully.');
    }
}
