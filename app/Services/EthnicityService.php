<?php

namespace App\Services;

use App\Models\Ethnicity;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EthnicityService
{
    public function paginate(array $filters)
    {
        return Ethnicity::query()

            ->when(

                $filters['search'] ?? null,

                function ($query, $search) {

                    $query->where(

                        'ethnicity',

                        'like',

                        "%{$search}%"

                    );

                }

            )

            ->orderBy(

                $filters['sort'] ?? 'ethnicity',

                $filters['direction'] ?? 'asc'

            )

            ->paginate(

                $filters['per_page'] ?? 10

            );
    }

    public function store(array $data): Ethnicity
    {
        return Ethnicity::create($data);
    }

    public function update(Ethnicity $ethnicity, array $data): Ethnicity
    {
        $ethnicity->update($data);

        return $ethnicity->refresh();
    }

    public function destroy(Ethnicity $ethnicity): void
    {
        $ethnicity->delete();
    }
}