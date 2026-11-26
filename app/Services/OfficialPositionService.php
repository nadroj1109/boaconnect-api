<?php

namespace App\Services;

use App\Models\OfficialPosition;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class OfficialPositionService
{
    public function paginate(
        array $filters
    ): LengthAwarePaginator {

        return OfficialPosition::query()
            ->when(
                $filters['search'] ?? null,

                function ($query, $search) {

                    $query->where(function ($query) use ($search) {

                        $query
                            ->where(
                                'name',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'description',
                                'like',
                                "%{$search}%"
                            );

                    });

                }
            )

            ->orderBy(
                $filters['sort'] ?? 'name',
                $filters['direction'] ?? 'asc'
            )

            ->paginate(
                $filters['per_page'] ?? 10
            );
    }

    public function store(
        array $data
    ): OfficialPosition {

        return OfficialPosition::create($data);
    }

    public function show(
        OfficialPosition $officialPosition
    ): OfficialPosition {

        return $officialPosition;
    }

    public function update(
        OfficialPosition $officialPosition,
        array $data
    ): OfficialPosition {

        $officialPosition->update($data);

        return $officialPosition->refresh();
    }

    public function destroy(
        OfficialPosition $officialPosition
    ): void {

        $officialPosition->delete();
    }

    public function available()
    {
        return OfficialPosition::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
