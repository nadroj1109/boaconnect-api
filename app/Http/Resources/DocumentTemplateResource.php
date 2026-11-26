<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentTemplateResource extends JsonResource
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

            'name' => $this->name,

            'description' => $this->description,

            'content' => $this->content,

            'page_size' => $this->page_size,

            'orientation' => $this->orientation,

            'is_active' => $this->is_active,

            'status' => $this->is_active
                ? 'Active'
                : 'Inactive',

            'document_types' =>
                $this->whenLoaded(
                    'documentTypes',
                    fn () =>
                        $this->documentTypes
                            ->map(fn ($documentType) => [
                                'id' => $documentType->id,
                                'code' => $documentType->code,
                                'name' => $documentType->name,

                                'fields' =>
                                    DocumentTypeFieldResource::collection(
                                        $documentType->fields
                                    ),
                            ])
                            ->values()
                ),

            'created_at' => $this->created_at
                ?->toISOString(),

            'updated_at' => $this->updated_at
                ?->toISOString(),
        ];
    }
}
