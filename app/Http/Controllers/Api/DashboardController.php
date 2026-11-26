<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {
    }

    public function index(Request $request)
    {
        $data = $this->dashboardService->getDashboard(
            $request->user(),
            $request->validate([
                'year' => [
                    'nullable',
                    'integer',
                    'min:2000',
                    'max:2100',
                ],

                'month' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:12',
                ],

                'barangay_id' => [
                    'nullable',
                    'integer',
                    'exists:barangays,id',
                ],
            ])
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
