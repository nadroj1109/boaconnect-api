<?php

namespace App\Services;

use App\Models\SitioPurok;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class SitioPurokService
{
    public function paginate(
        array $filters
    ): LengthAwarePaginator {

        return SitioPurok::query()

            ->where('barangay_id', Auth::user()->barangay_id)

            ->when(
                $filters['search'] ?? null,

                function ($query, $search) {

                    $query->where(function ($query) use ($search) {

                        $query
                            ->where(
                                'sitio_purok',
                                'like',
                                "%{$search}%"
                            );

                    });

                }
            )

            ->orderBy(
                $filters['sort'] ?? 'sitio_purok',
                $filters['direction'] ?? 'asc'
            )

            ->paginate(
                $filters['per_page'] ?? 10
            );
    }

    public function store(
        array $data
    ): SitioPurok {

        return SitioPurok::create([
            'barangay_id' => Auth::user()->barangay_id,
            ...$data,
        ]);
    }

    public function show(
        SitioPurok $sitioPurok
    ): SitioPurok {

        return $sitioPurok;
    }

    public function update(
        SitioPurok $sitioPurok,
        array $data
    ): SitioPurok {

        $sitioPurok->update($data);

        return $sitioPurok->refresh();
    }

    public function destroy(
        SitioPurok $sitioPurok
    ): void {

        $sitioPurok->delete();
    }

    public function available()
    {
        return SitioPurok::query()
            ->where('is_active', true)
            ->orderBy('sitio_purok')
            ->get();
    }
}