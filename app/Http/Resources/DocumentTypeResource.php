<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentTypeResource extends JsonResource
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

            'code' => $this->code,

            'name' => $this->name,
            'document_title' => $this->document_title,

            'description' => $this->description,

            'fee' => $this->fee ?? number_format(0,2),

            'requires_payment' => $this->requiresPayment(),
            'payment_required' => $this->requiresPayment() ? 'Yes' : 'No',

            'allow_online_payment' => $this->allowsOnlinePayment(),
            'online_payment_allowed' => $this->allowsOnlinePayment() ? 'Yes' : 'No',

            'requires_chairperson_signature' => $this->requiresChairpersonSignature(),
            'chairperson_signature_required' => $this->requiresChairpersonSignature() ? 'Yes' : 'No',

            /*
            |--------------------------------------------------------------------------
            | Document Template
            |--------------------------------------------------------------------------
            */

            'document_template_id' =>
                $this->document_template_id,

            'document_template' =>
                $this->whenLoaded(
                    'documentTemplate',
                    fn () => [
                        'id' =>
                            $this->documentTemplate->id,

                        'name' =>
                            $this->documentTemplate->name,
                    ]
                ),

            /*
            |--------------------------------------------------------------------------
            | Fields
            |--------------------------------------------------------------------------
            */

            'fields' =>
                DocumentTypeFieldResource::collection(
                    $this->whenLoaded('fields')
                ),

            /*
            |--------------------------------------------------------------------------
            | Requirements
            |--------------------------------------------------------------------------
            */

            'requirements' =>
                DocumentTypeRequirementResource::collection(
                    $this->whenLoaded('requirements')
                ),

            'template' => $this->template,

            'processing_days' => $this->processing_days,

            'is_active' => $this->is_active,
            'status' => $this->is_active ? 'Active' : 'Inactive',

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
