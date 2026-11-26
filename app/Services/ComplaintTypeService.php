<?php

namespace App\Services;

use App\Models\ComplaintType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class ComplaintTypeService
{
    public function paginate(
        array $filters
    ): LengthAwarePaginator {

        return ComplaintType::query()

            ->when(
                $filters['search'] ?? null,

                function ($query, $search) {

                    $query->where(function ($query) use ($search) {

                        $query
                            ->where(
                                'name',
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
    ): ComplaintType {

        return ComplaintType::create($data);
    }

    public function show(
        ComplaintType $complaintType
    ): ComplaintType {

        return $complaintType;
    }

    public function update(
        ComplaintType $complaintType,
        array $data
    ): ComplaintType {

        $complaintType->update($data);

        return $complaintType->refresh();
    }

    public function destroy(
        ComplaintType $complaintType
    ): void {

        $complaintType->delete();
    }

    public function available()
    {
        return ComplaintType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
