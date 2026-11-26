<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentTypeRequirementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'document_type_id' =>
                $this->document_type_id,

            'name' =>
                $this->name,

            'description' =>
                $this->description,

            'is_required' =>
                $this->is_required,

            'required' =>
                $this->is_required
                    ? 'Yes'
                    : 'No',

            'allowed_file_types' =>
                $this->allowed_file_types,

            'allowed_file_types_mobile' =>
                collect(
                    explode(
                        ',',
                        $this->allowed_file_types ?? ''
                    )
                )
                    ->map(
                        fn ($type) =>
                            match (
                                strtolower(trim($type))
                            ) {
                                'pdf' =>
                                    'application/pdf',

                                'jpg',
                                'jpeg' =>
                                    'image/jpeg',

                                'png' =>
                                    'image/png',

                                'gif' =>
                                    'image/gif',

                                'webp' =>
                                    'image/webp',

                                'doc' =>
                                    'application/msword',

                                'docx' =>
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',

                                'xls' =>
                                    'application/vnd.ms-excel',

                                'xlsx' =>
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',

                                'txt' =>
                                    'text/plain',

                                'csv' =>
                                    'text/csv',

                                'zip' =>
                                    'application/zip',

                                default =>
                                    null,
                            }
                    )
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),

            'max_file_size' =>
                $this->max_file_size,

            'max_file_size_mb' =>
                $this->max_file_size
                    ? round(
                        $this->max_file_size / 1024,
                        2
                    )
                    : null,

            'sort_order' =>
                $this->sort_order,

            'is_active' =>
                $this->is_active,

            'status' =>
                $this->is_active
                    ? 'Active'
                    : 'Inactive',

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}
