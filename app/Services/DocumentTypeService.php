<?php

namespace App\Services;

use App\Models\DocumentType;
use App\Models\Resident;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class DocumentTypeService
{
    public function paginate(
        array $filters
    ): LengthAwarePaginator {

        return DocumentType::query()

            // ->where(
            //     'barangay_id',
            //     Auth::user()->barangay_id
            // )

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
                                'code',
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
    ): DocumentType {
        $data['code'] = $this->generateCode(
            $data['name']
        );
        return DocumentType::create($data);
    }

    public function show(
        DocumentType $documentType
    ): DocumentType {

        return $documentType->load([
            'documentTemplate',
            'fields',
        ]);
    }

    public function update(
        DocumentType $documentType,
        array $data
    ): DocumentType {

        $documentType->update($data);

        return $documentType->refresh();
    }

    public function destroy(
        DocumentType $documentType
    ): void {

        $documentType->delete();
    }

    public function available(?int $residentId = null)
    {
        $cityId = null;

        if ($residentId) {

            $user = Auth::user();

            $authenticatedResident =
                $user?->resident;

            $resident = Resident::query()
                ->findOrFail($residentId);

            if (
                $authenticatedResident
                &&
                $resident->household_id !==
                    $authenticatedResident->household_id
            ) {
                throw ValidationException::withMessages([
                    'resident_id' =>
                        'The selected resident does not belong to your household.',
                ]);
            }

            $cityId = $resident->city_id;
        }

        request()->attributes->set(
            'document_field_city_id',
            $cityId
        );
    
        return DocumentType::query()
            ->where('is_active', true)
            ->with([
                'documentTemplate',
                'fields',
                'requirements',
            ])
            ->orderBy('name')
            ->get();
    }

    protected function generateCode(string $name): string
    {
        $words = preg_split(
            '/\s+/',
            trim($name)
        );

        $prefix = collect($words)
            ->filter()
            ->map(function ($word) {

                return strtoupper(
                    substr(
                        preg_replace(
                            '/[^A-Za-z0-9]/',
                            '',
                            $word
                        ),
                        0,
                        1
                    )
                );

            })
            ->implode('');

        if ($prefix === '') {
            $prefix = 'DOC';
        }

        $existingCodes = DocumentType::query()
            ->where(
                'code',
                'like',
                $prefix . '-%'
            )
            ->pluck('code');

        $nextNumber = 1;

        foreach ($existingCodes as $code) {

            if (
                preg_match(
                    '/^' . preg_quote($prefix, '/') . '-(\d+)$/',
                    $code,
                    $matches
                )
            ) {
                $number = (int) $matches[1];

                if ($number >= $nextNumber) {
                    $nextNumber = $number + 1;
                }
            }
        }

        return sprintf(
            '%s-%03d',
            $prefix,
            $nextNumber
        );
    }
}