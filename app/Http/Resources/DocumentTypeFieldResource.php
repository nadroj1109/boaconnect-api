<?php

namespace App\Http\Resources;

use App\Services\DocumentFieldOptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentTypeFieldResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $optionService = app(
            DocumentFieldOptionService::class
        );

        return [
            'id' =>
                $this->id,

            'document_type_id' =>
                $this->document_type_id,

            'name' =>
                $this->name,

            'description' =>
                $this->description,

            'field_key' =>
                $this->field_key,

            'field_type' =>
                $this->field_type,

            'is_required' =>
                $this->is_required,

            'required' =>
                $this->is_required,

            'option_source' =>
                $this->option_source,
                
            'options' =>
                $optionService->resolve(
                    $this->resource,
                    $request->attributes->get(
                        'document_field_city_id'
                    )
                ),

            'sort_order' =>
                $this->sort_order,

            'is_active' =>
                $this->is_active,

            'status' =>
                $this->is_active
                    ? 'Active'
                    : 'Inactive',

            'variable' =>
                '{{ fields.' .
                $this->field_key .
                ' }}',

            'created_at' =>
                $this->created_at,

            'updated_at' =>
                $this->updated_at,
        ];
    }
}
