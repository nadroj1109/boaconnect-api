<?php

namespace App\Services;

use App\Models\Household;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class HouseholdService
{

	public function paginate(array $filters): LengthAwarePaginator
    {
        return Household::query()
            ->where('barangay_id', Auth::user()->barangay_id)
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('household_name', 'like', "%{$search}%")
                        ->orWhere('household_no', 'like', "%{$search}%")
                        ->orWhere('complete_address', 'like', "%{$search}%");
                });
            })

            ->orderBy(

                $filters['sort'] ?? 'household_name',

                $filters['direction'] ?? 'asc'

            )

            ->paginate(

                $filters['per_page'] ?? 10

            );
    }

    public function store(array $data): Household
    {
        return Household::create([
            ...$data,
            'barangay_id' => Auth::user()->barangay_id,
        ]);
    }

    public function show(Household $household): Household
    {
        return $household;
    }

    public function update(Household $household, array $data): Household
    {
        $household->update($data);

        return $household->refresh();
    }
}