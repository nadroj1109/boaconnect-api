<?php

namespace App\Services;

use App\Models\BarangayOfficial;
use App\Models\Resident;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BarangayOfficialService
{
    public function paginate(
        array $filters
    ): LengthAwarePaginator {

        return BarangayOfficial::query()

            ->with([
                'barangay',
                'resident',
                'position',
            ])

            ->when(
                $filters['search'] ?? null,

                function ($query, $search) {

                    $query->where(function ($query) use ($search) {

                        $query
                            ->whereHas(
                                'resident',
                                function ($query) use ($search) {

                                    $query->where(
                                        'full_name',
                                        'like',
                                        "%{$search}%"
                                    );

                                }
                            )

                            ->orWhereHas(
                                'position',
                                function ($query) use ($search) {

                                    $query->where(
                                        'name',
                                        'like',
                                        "%{$search}%"
                                    );

                                }
                            );

                    });

                }
            )

            ->when(
                $filters['barangay_id'] ?? null,

                fn ($query, $barangayId) =>
                    $query->where(
                        'barangay_id',
                        $barangayId
                    )
            )

            ->when(
                $filters['official_position_id'] ?? null,

                fn ($query, $positionId) =>
                    $query->where(
                        'official_position_id',
                        $positionId
                    )
            )

            ->when(
                isset($filters['is_active']),

                fn ($query) =>
                    $query->where(
                        'is_active',
                        $filters['is_active']
                    )
            )

            ->orderBy(
                $filters['sort'] ?? 'term_start',
                $filters['direction'] ?? 'desc'
            )

            ->paginate(
                $filters['per_page'] ?? 10
            );
    }

    public function store(
        array $data
    ): BarangayOfficial {

        return BarangayOfficial::create($data);
    }

    public function show(
        BarangayOfficial $barangayOfficial
    ): BarangayOfficial {

        return $barangayOfficial->load([
            'barangay',
            'resident',
            'position',
        ]);
    }

    public function update(
        BarangayOfficial $barangayOfficial,
        array $data
    ): BarangayOfficial {

        $barangayOfficial->update($data);

        return $barangayOfficial
            ->refresh()
            ->load([
                'barangay',
                'resident',
                'position',
            ]);
    }

    public function destroy(
        BarangayOfficial $barangayOfficial
    ): void {

        $barangayOfficial->delete();
    }

    public function available()
    {
        return BarangayOfficial::query()
            ->with([
                'resident',
                'position',
            ])
            ->where('is_active', true)
            ->orderBy(
                'term_start',
                'desc'
            )
            ->get();
    }

    public function residents(
        int $barangayId
    ) {

        return Resident::query()

            ->whereHas(
                'household',
                function ($household) use ($barangayId) {

                    $household->where(
                        'barangay_id',
                        $barangayId
                    );

                }
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(
                fn ($resident) => [
                    'id' => $resident->id,
                    'value' => $resident->id,
                    'label' => $resident->full_name,
                    'email' => $resident->email_address,
                    'mobile_number' => $resident->mobile_no,
                ]
            )
            ->values();
    }

    public function availableForUser(
        int $barangayId,
        ?int $userId = null
    ) {
        return BarangayOfficial::query()
            ->with([
                'resident',
                'position',
            ])
            ->where('barangay_id', $barangayId)
            ->where('is_active', true)
            ->whereHas('resident')
            ->where(function ($query) use ($userId) {

                // Officials without a User account are available.
                $query->whereDoesntHave('resident.user');

                // During Edit, also include this User's own official.
                if ($userId !== null) {
                    $query->orWhereHas(
                        'resident.user',
                        function ($userQuery) use ($userId) {
                            $userQuery->where('users.id', $userId);
                        }
                    );
                }
            })
            ->orderBy('term_start', 'desc')
            ->get();
    }

    public function assignable(
        int $barangayId
    ) {
        return BarangayOfficial::query()

            ->with([
                'resident.user',
                'position',
            ])

            ->where(
                'barangay_id',
                $barangayId
            )

            ->where(
                'is_active',
                true
            )

            ->whereHas(
                'resident.user'
            )

            ->orderBy(
                'term_start',
                'desc'
            )

            ->get();
    }
}