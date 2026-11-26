<?php

namespace App\Services;

use App\Models\Barangay;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BarangayService
{
    public function paginate(array $filters)
    {
        return Barangay::query()

            ->with('city')
            ->where('city_id', 599)

            ->when(

                $filters['search'] ?? null,

                function ($query, $search) {

                    $query->where(

                        'barangay',

                        'like',

                        "%{$search}%"

                    );

                }

            )

            ->orderBy(

                $filters['sort'] ?? 'barangay',

                $filters['direction'] ?? 'asc'

            )

            ->paginate(

                $filters['per_page'] ?? 10

            );
    }

    public function store(array $data): Barangay
    {
        return Barangay::create([
            ...$data,
            'city_id' => 599,
        ]);
    }

    public function update(Barangay $barangay, array $data): Barangay
    {
        $barangay->update($data);

        return $barangay->refresh();
    }

    public function destroy(Barangay $barangay): void
    {
        $barangay->delete();
    }

    public function options()
    {
        return Barangay::query()

            ->where('city_id', 599)

            ->orderBy('barangay')

            ->get([
                'id',
                'barangay',
            ]);
    }
}