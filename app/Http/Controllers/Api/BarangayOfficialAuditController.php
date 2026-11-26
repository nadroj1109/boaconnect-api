<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BarangayOfficial\BarangayOfficialAuditIndexRequest;
use App\Http\Resources\BarangayOfficialAuditResource;
use App\Models\BarangayOfficial;
use App\Services\BarangayOfficial\BarangayOfficialAuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class BarangayOfficialAuditController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected BarangayOfficialAuditService $service
    ) {}

    public function index(
        BarangayOfficialAuditIndexRequest $request,
        BarangayOfficial $barangayOfficial
    ) {
        $user = $request->user();

        if (
            !$user->hasAnyRole([
                'Super Administrator',
                'Administrator',
            ]) &&
            (int) $user->barangay_id !==
                (int) $barangayOfficial->barangay_id
        ) {
            abort(
                403,
                'You cannot view this official\'s audit history.'
            );
        }

        $audits = $this->service->paginate(
            $barangayOfficial,
            $request->validated()
        );

        return $this->paginated(
            $audits,
            BarangayOfficialAuditResource::collection($audits),
            'Barangay Official audit history retrieved successfully.'
        );
    }
}
