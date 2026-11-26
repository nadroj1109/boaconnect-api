<?php

namespace App\Services;

use App\Constants\Roles;
use App\Enums\ComplaintStatus;
use App\Enums\DocumentRequestStatus;
use App\Enums\HighestEducationalAttainment;
use App\Models\Complaint;
use App\Models\DocumentRequest;
use App\Models\Household;
use App\Models\Resident;
use App\Models\ResidentSector;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getDashboard(
        User $user,
        array $filters = []
    ): array {
        $year = (int) ($filters['year'] ?? now()->year);

        $month = isset($filters['month']) && $filters['month'] !== ''
            ? (int) $filters['month']
            : null;

        /*
        |--------------------------------------------------------------------------
        | Dashboard Scope
        |--------------------------------------------------------------------------
        */

        $isMunicipal = $user->hasAnyRole([
            Roles::SUPER_ADMIN,
            Roles::ADMINISTRATOR,
        ]);

        if ($isMunicipal) {

            // Super Administrator / Administrator
            // may optionally filter by barangay.
            $barangayId = !empty($filters['barangay_id'])
                ? (int) $filters['barangay_id']
                : null;

        } else {

            // Chairperson / Secretary / Staff
            // are always restricted to their own barangay.
            $barangayId = $user->barangay_id;
        }

        /*
        |--------------------------------------------------------------------------
        | Base Queries
        |--------------------------------------------------------------------------
        |
        | Households / Residents
        | ----------------------
        | These represent the currently recorded population/household data.
        | They are intentionally NOT filtered by year/month.
        |
        | Complaints / Documents
        | ----------------------
        | These are activity-based statistics and are filtered by the
        | selected year and optional period.
        |
        */

        $householdQuery = Household::query();

        $residentQuery = Resident::query();

        $complaintQuery = Complaint::query()
            ->whereYear(
                'complaints.created_at',
                $year
            );

        $documentQuery = DocumentRequest::query()
            ->whereYear(
                'document_requests.requested_at',
                $year
            );

        /*
        |--------------------------------------------------------------------------
        | Monthly Queries
        |--------------------------------------------------------------------------
        |
        | These are intentionally separate from the filtered activity queries.
        |
        | If the user selects "March", the summary cards show March data,
        | but the monthly chart still shows January through December for
        | the selected year.
        |
        */

        $monthlyComplaintQuery = Complaint::query()
            ->whereYear(
                'complaints.created_at',
                $year
            );

        $monthlyDocumentQuery = DocumentRequest::query()
            ->whereYear(
                'document_requests.requested_at',
                $year
            );

        /*
        |--------------------------------------------------------------------------
        | Period Filter
        |--------------------------------------------------------------------------
        */

        if ($month) {

            $complaintQuery->whereMonth(
                'complaints.created_at',
                $month
            );

            $documentQuery->whereMonth(
                'document_requests.requested_at',
                $month
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Barangay Scope
        |--------------------------------------------------------------------------
        */

        if ($barangayId) {

            $householdQuery->where(
                'households.barangay_id',
                $barangayId
            );

            $residentQuery->where(
                'residents.barangay_id',
                $barangayId
            );

            $complaintQuery->where(
                'complaints.barangay_id',
                $barangayId
            );

            $documentQuery->where(
                'document_requests.barangay_id',
                $barangayId
            );

            $monthlyComplaintQuery->where(
                'complaints.barangay_id',
                $barangayId
            );

            $monthlyDocumentQuery->where(
                'document_requests.barangay_id',
                $barangayId
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Return Dashboard
        |--------------------------------------------------------------------------
        */

        return [

            'scope' => [
                'type' => $barangayId
                    ? 'barangay'
                    : 'municipal',

                'barangay_id' => $barangayId,
            ],

            'filters' => [
                'year' => $year,
                'month' => $month,
                'barangay_id' => $barangayId,
            ],

            'households' =>
                $this->householdStatistics(
                    $householdQuery
                ),

            'residents' =>
                $this->residentStatistics(
                    $residentQuery,
                    $barangayId
                ),

            'complaints' =>
                $this->complaintStatistics(
                    $complaintQuery,
                    $monthlyComplaintQuery,
                    $barangayId,
                    $year,
                    $month
                ),

            'document_requests' =>
                $this->documentRequestStatistics(
                    $documentQuery,
                    $monthlyDocumentQuery
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Household Statistics
    |--------------------------------------------------------------------------
    */

    protected function householdStatistics(
        $query
    ): array {

        return [

            'total' =>
                (clone $query)->count(),

            'total_families' =>
                (clone $query)->sum('no_of_family'),

            'total_members' =>
                (clone $query)->sum('no_of_hh_member'),

        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Resident Statistics
    |--------------------------------------------------------------------------
    */

    protected function residentStatistics(
        $residentQuery,
        ?int $barangayId
    ): array {

        $totalResidents =
            (clone $residentQuery)->count();

        /*
        |--------------------------------------------------------------------------
        | Sector Statistics
        |--------------------------------------------------------------------------
        */

        $sectorCounts = ResidentSector::query()

            ->where('registered', true)

            ->whereHas(
                'resident',
                function ($query) use ($barangayId) {

                    $query->when(
                        $barangayId,
                        fn ($query) =>
                            $query->where(
                                'barangay_id',
                                $barangayId
                            )
                    );

                }
            )

            ->join(
                'sectors',
                'sectors.id',
                '=',
                'resident_sectors.sector_id'
            )

            ->select(
                'sectors.sector_code',
                DB::raw(
                    'COUNT(DISTINCT resident_sectors.resident_id) as total'
                )
            )

            ->groupBy('sectors.sector_code')

            ->pluck(
                'total',
                'sectors.sector_code'
            );

        /*
        |--------------------------------------------------------------------------
        | Sex Statistics
        |--------------------------------------------------------------------------
        */

        $sexCounts =
            (clone $residentQuery)

                ->whereNotNull('sex')

                ->select(
                    'sex',
                    DB::raw('COUNT(*) as total')
                )

                ->groupBy('sex')

                ->get()

                ->mapWithKeys(function ($item) {

                    $value = $item->sex;

                    if ($value instanceof \BackedEnum) {
                        $value = $value->value;
                    }

                    return [
                        strtolower((string) $value) =>
                            (int) $item->total,
                    ];

                });

        /*
        |--------------------------------------------------------------------------
        | Age Group Statistics
        |--------------------------------------------------------------------------
        */

        $ageGroups = [

            [
                'key' => '0_5',
                'label' => '0–5',
                'min' => 0,
                'max' => 5,
            ],

            [
                'key' => '6_12',
                'label' => '6–12',
                'min' => 6,
                'max' => 12,
            ],

            [
                'key' => '13_17',
                'label' => '13–17',
                'min' => 13,
                'max' => 17,
            ],

            [
                'key' => '18_24',
                'label' => '18–24',
                'min' => 18,
                'max' => 24,
            ],

            [
                'key' => '25_34',
                'label' => '25–34',
                'min' => 25,
                'max' => 34,
            ],

            [
                'key' => '35_44',
                'label' => '35–44',
                'min' => 35,
                'max' => 44,
            ],

            [
                'key' => '45_59',
                'label' => '45–59',
                'min' => 45,
                'max' => 59,
            ],

            [
                'key' => '60_plus',
                'label' => '60+',
                'min' => 60,
                'max' => null,
            ],

        ];

        $today = now()->startOfDay();

        $ageStatistics =
            collect($ageGroups)
                ->map(function ($group) use (
                    $residentQuery,
                    $today
                ) {

                    $query = clone $residentQuery;

                    /*
                    |--------------------------------------------------------------------------
                    | Maximum DOB for minimum age
                    |--------------------------------------------------------------------------
                    |
                    | Example:
                    | Age 18 today means DOB <= today - 18 years.
                    |
                    */

                    $maxDateOfBirth =
                        $today->copy()->subYears(
                            $group['min']
                        );

                    if ($group['max'] !== null) {

                        /*
                        |--------------------------------------------------------------------------
                        | Minimum DOB for maximum age
                        |--------------------------------------------------------------------------
                        |
                        | Example:
                        | Age 18–24:
                        |
                        | DOB > today - 25 years
                        | DOB <= today - 18 years
                        |
                        */

                        $minDateOfBirth =
                            $today->copy()->subYears(
                                $group['max'] + 1
                            );

                        $query
                            ->where(
                                'date_of_birth',
                                '>',
                                $minDateOfBirth
                            )
                            ->where(
                                'date_of_birth',
                                '<=',
                                $maxDateOfBirth
                            );

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | 60+
                        |--------------------------------------------------------------------------
                        */

                        $query->where(
                            'date_of_birth',
                            '<=',
                            $maxDateOfBirth
                        );
                    }

                    return [
                        'key' => $group['key'],
                        'label' => $group['label'],
                        'total' => (int) $query->count(),
                    ];
                })
                ->values();

        /*
        |--------------------------------------------------------------------------
        | Educational Attainment
        |--------------------------------------------------------------------------
        */

        $educationalAttainment =
            collect(HighestEducationalAttainment::cases())
                ->map(function (
                    HighestEducationalAttainment $attainment
                ) use ($residentQuery) {

                    $total =
                        (clone $residentQuery)
                            ->where(
                                'highest_educ_attainment',
                                $attainment->value
                            )
                            ->count();

                    return [
                        'value' => $attainment->value,
                        'label' => $attainment->label(),
                        'total' => (int) $total,
                    ];
                })
                ->values();

        /*
        |--------------------------------------------------------------------------
        | Ethnicity Distribution
        |--------------------------------------------------------------------------
        */

        $ethnicities =
            (clone $residentQuery)

                ->whereNotNull('ethnicity_id')

                ->join(
                    'ethnicities',
                    'ethnicities.id',
                    '=',
                    'residents.ethnicity_id'
                )

                ->select(
                    'ethnicities.id',
                    'ethnicities.ethnicity',
                    DB::raw('COUNT(*) as total')
                )

                ->groupBy(
                    'ethnicities.id',
                    'ethnicities.ethnicity'
                )

                ->orderByDesc('total')
                ->orderBy('ethnicities.ethnicity')

                ->get()

                ->map(function ($item) {

                    return [
                        'id' => $item->id,
                        'name' => $item->ethnicity,
                        'total' => (int) $item->total,
                    ];

                })

                ->values();

        /*
        |--------------------------------------------------------------------------
        | Return
        |--------------------------------------------------------------------------
        */

        return [

            'total' =>
                $totalResidents,

            'sex' => [

                'male' =>
                    (int) (
                        $sexCounts['male'] ?? 0
                    ),

                'female' =>
                    (int) (
                        $sexCounts['female'] ?? 0
                    ),

            ],

            'age_groups' =>
                $ageStatistics,

            'sectors' => [

                'employed' =>
                    (int) (
                        $sectorCounts['employed'] ?? 0
                    ),

                'unemployed' =>
                    (int) (
                        $sectorCounts['unemployed'] ?? 0
                    ),

                'senior_citizen' =>
                    (int) (
                        $sectorCounts['senior_citizen'] ?? 0
                    ),

                'pwd' =>
                    (int) (
                        $sectorCounts['pwd'] ?? 0
                    ),

                'indigenous' =>
                    (int) (
                        $sectorCounts['indigenous'] ?? 0
                    ),

                'student' =>
                    (int) (
                        $sectorCounts['student'] ?? 0
                    ),

                'solo_parent' =>
                    (int) (
                        $sectorCounts['solo_parent'] ?? 0
                    ),

            ],

            'educational_attainment' =>
                $educationalAttainment,

            'ethnicities' => $ethnicities,

        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Complaint Statistics
    |--------------------------------------------------------------------------
    */

    protected function complaintStatistics(
        $query,
        $monthlyQuery,
        ?int $barangayId,
        int $year,
        ?int $month
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Summary Statistics
        |--------------------------------------------------------------------------
        */

        $total =
            (clone $query)->count();

        $resolved =
            (clone $query)
                ->where(
                    'status',
                    ComplaintStatus::RESOLVED
                )
                ->count();

        $closed =
            (clone $query)
                ->where(
                    'status',
                    ComplaintStatus::CLOSED
                )
                ->count();

        $unresolved =
            (clone $query)
                ->whereNotIn(
                    'status',
                    [
                        ComplaintStatus::RESOLVED,
                        ComplaintStatus::CLOSED,
                        ComplaintStatus::REJECTED,
                        ComplaintStatus::CANCELLED,
                    ]
                )
                ->count();

        /*
        |--------------------------------------------------------------------------
        | By Status
        |--------------------------------------------------------------------------
        */

        $byStatus =
            (clone $query)

                ->select(
                    'status',
                    DB::raw('COUNT(*) as total')
                )

                ->groupBy('status')

                ->get()

                ->map(function ($item) {

                    $status =
                        $item->status;

                    return [

                        'status' =>
                            $status instanceof \BackedEnum
                                ? $status->value
                                : $status,

                        'label' =>
                            $status instanceof \BackedEnum
                                && method_exists(
                                    $status,
                                    'label'
                                )
                                ? $status->label()
                                : $status,

                        'total' =>
                            (int) $item->total,

                    ];

                })

                ->values();

        /*
        |--------------------------------------------------------------------------
        | By Complaint Type
        |--------------------------------------------------------------------------
        */

        $byType =
            DB::table('complaint_types')
                ->leftJoin(
                    'complaints',
                    function ($join) use (
                        $barangayId,
                        $year,
                        $month
                    ) {

                        $join->on(
                            'complaint_types.id',
                            '=',
                            'complaints.complaint_type_id'
                        );

                        $join->whereNull(
                            'complaints.deleted_at'
                        );

                        $join->whereYear(
                            'complaints.created_at',
                            $year
                        );

                        if ($month) {
                            $join->whereMonth(
                                'complaints.created_at',
                                $month
                            );
                        }

                        if ($barangayId) {
                            $join->where(
                                'complaints.barangay_id',
                                $barangayId
                            );
                        }
                    }
                )
                ->select(
                    'complaint_types.id',
                    'complaint_types.name',
                    DB::raw(
                        'COUNT(complaints.id) as total'
                    )
                )
                ->groupBy(
                    'complaint_types.id',
                    'complaint_types.name'
                )
                ->orderByDesc('total')
                ->orderBy('complaint_types.name')
                ->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'total' => (int) $item->total,
                ])
                ->values();

        /*
        |--------------------------------------------------------------------------
        | Monthly Statistics
        |--------------------------------------------------------------------------
        */

        $monthly = collect(range(1, 12))

            ->map(function ($month) use ($monthlyQuery) {

                $total =
                    (clone $monthlyQuery)
                        ->whereMonth(
                            'complaints.created_at',
                            $month
                        )
                        ->count();

                return [

                    'month' =>
                        $month,

                    'label' =>
                        Carbon::create()
                            ->month($month)
                            ->format('F'),

                    'total' =>
                        $total,

                ];
            })

            ->values();

        /*
        |--------------------------------------------------------------------------
        | Return
        |--------------------------------------------------------------------------
        */

        return [

            'total' =>
                $total,

            'resolved' =>
                $resolved,

            'closed' =>
                $closed,

            'unresolved' =>
                $unresolved,

            'resolution_rate' =>
                $total > 0

                    ? round(
                        (($resolved + $closed) / $total) * 100,
                        2
                    )

                    : 0,

            'by_status' =>
                $byStatus,

            'by_type' =>
                $byType,

            'monthly' =>
                $monthly,

        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Document Request Statistics
    |--------------------------------------------------------------------------
    */

    protected function documentRequestStatistics(
        $query,
        $monthlyQuery
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Total
        |--------------------------------------------------------------------------
        */

        $total =
            (clone $query)->count();

        /*
        |--------------------------------------------------------------------------
        | Status Statistics
        |--------------------------------------------------------------------------
        */

        $statuses = [];

        foreach (
            DocumentRequestStatus::cases()
            as $status
        ) {

            $statuses[$status->value] = [

                'label' =>
                    $status->label(),

                'total' =>
                    (clone $query)
                        ->where(
                            'status',
                            $status
                        )
                        ->count(),

            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Most Requested Documents
        |--------------------------------------------------------------------------
        */

        $byDocumentType =
            (clone $query)

                ->join(
                    'document_types',
                    'document_types.id',
                    '=',
                    'document_requests.document_type_id'
                )

                ->select(
                    'document_types.id',
                    'document_types.name',
                    DB::raw('COUNT(*) as total')
                )

                ->groupBy(
                    'document_types.id',
                    'document_types.name'
                )

                ->orderByDesc('total')

                ->get()

                ->map(fn ($item) => [

                    'id' =>
                        $item->id,

                    'name' =>
                        $item->name,

                    'total' =>
                        (int) $item->total,

                ])

                ->values();

        /*
        |--------------------------------------------------------------------------
        | Document Requests by Barangay
        |--------------------------------------------------------------------------
        */

        $byBarangay =
            (clone $query)

                ->join(
                    'barangays',
                    'barangays.id',
                    '=',
                    'document_requests.barangay_id'
                )

                ->select(
                    'barangays.id',
                    'barangays.barangay',
                    DB::raw('COUNT(*) as total')
                )

                ->groupBy(
                    'barangays.id',
                    'barangays.barangay'
                )

                ->orderByDesc('total')

                ->get()

                ->map(fn ($item) => [

                    'id' =>
                        $item->id,

                    'name' =>
                        $item->barangay,

                    'total' =>
                        (int) $item->total,

                ])

                ->values();

        /*
        |--------------------------------------------------------------------------
        | Monthly Statistics
        |--------------------------------------------------------------------------
        */

        $monthly = collect(range(1, 12))

            ->map(function ($month) use ($monthlyQuery) {

                $total =
                    (clone $monthlyQuery)
                        ->whereMonth(
                            'document_requests.requested_at',
                            $month
                        )
                        ->count();

                return [

                    'month' =>
                        $month,

                    'label' =>
                        Carbon::create()
                            ->month($month)
                            ->format('F'),

                    'total' =>
                        $total,

                ];
            })

            ->values();

        /*
        |--------------------------------------------------------------------------
        | Return
        |--------------------------------------------------------------------------
        */

        return [

            'total' =>
                $total,

            'statuses' =>
                $statuses,

            'by_document_type' =>
                $byDocumentType,

            'by_barangay' =>
                $byBarangay,

            'monthly' =>
                $monthly,

        ];
    }
}