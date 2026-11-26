<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\DocumentTypeField;

class DocumentFieldOptionService
{
    /**
     * Resolve options for a document type field.
     *
     * @return array<int, mixed>
     */
    public function resolve(
        DocumentTypeField $field,
        ?int $cityId = null
    ): array {
        if (! $field->option_source) {
            return $field->options ?? [];
        }

        return match ($field->option_source) {
            'barangays' => $this->barangays($cityId),
            default => [],
        };
    }

    /**
     * Resolve a submitted field value into
     * a human-readable value.
     */
    public function resolveValue(
        DocumentTypeField $field,
        mixed $value,
        ?int $cityId = null
    ): mixed {
        if (
            $value === null ||
            $value === ''
        ) {
            return $value;
        }

        if (! $field->option_source) {
            return $value;
        }

        return match ($field->option_source) {
            'barangays' => $this->resolveBarangayValue(
                $value,
                $cityId
            ),

            default => $value,
        };
    }

    /**
     * Resolve a barangay value.
     */
    protected function resolveBarangayValue(
        mixed $value,
        ?int $cityId = null
    ): mixed {
        $barangay = Barangay::query()
            ->with([
                'city.province',
            ])
            ->where('is_active', true)
            ->when(
                $cityId,
                fn ($query) =>
                    $query->where(
                        'city_id',
                        $cityId
                    )
            )
            ->find($value);

        if (! $barangay) {
            return $value;
        }

        return collect([
            $barangay->barangay,
            $barangay->city?->city,
            $barangay->city?->province?->province,
        ])
            ->filter(
                fn ($value) =>
                    $value !== null &&
                    $value !== ''
            )
            ->implode(', ');
    }

    /**
     * Resolve barangay options.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function barangays(
        ?int $cityId = null
    ): array {
        return Barangay::query()
            ->where('is_active', true)
            ->when(
                $cityId,
                fn ($query) =>
                    $query->where(
                        'city_id',
                        $cityId
                    )
            )
            ->orderBy('barangay')
            ->get([
                'id',
                'barangay',
            ])
            ->map(
                fn (Barangay $barangay) => [
                    'value' =>
                        $barangay->id,

                    'label' =>
                        $barangay->barangay,
                ]
            )
            ->values()
            ->all();
    }
}
