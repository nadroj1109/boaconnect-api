<?php

namespace App\Http\Resources;

use App\Http\Resources\DocumentRequestAttachmentResource;
use App\Models\Barangay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' =>
                $this->id,

            'request_no' =>
                $this->request_no,

            'document_no' =>
                $this->document_no,

            'status' =>
                $this->status?->value,

            'status_label' =>
                $this->status?->label(),

            'delivery_method' =>
                $this->delivery_method?->value,

            'delivery_method_label' =>
                $this->delivery_method?->label(),

            'purpose' =>
                $this->purpose,

            'remarks' =>
                $this->remarks,


            /*
             * Document configuration
             */

            'document_type' =>
                $this->whenLoaded(
                    'documentType',
                    fn () =>
                        new DocumentTypeResource(
                            $this->documentType
                        )
                ),

            'document_template' =>
                $this->whenLoaded(
                    'documentTemplate',
                    fn () =>
                        $this->documentTemplate
                            ? new DocumentTemplateResource(
                                $this->documentTemplate
                            )
                            : null
                ),


            /*
             * Requester
             */

            'requester' =>
                $this->whenLoaded(
                    'resident',
                    function () {

                        return $this->resident
                            ? [
                                'id' =>
                                    $this->resident->id,

                                'name' =>
                                    $this->resident->full_name,
                            ]
                            : null;
                    }
                ),

            'requested_for' =>
                $this->whenLoaded(
                    'requestedFor',
                    function () {

                        return $this->requestedFor
                            ? [
                                'id' =>
                                    $this->requestedFor->id,

                                'name' =>
                                    $this->requestedFor->full_name,
                            ]
                            : null;
                    }
                ),


            /*
             * Lifecycle timestamps
             */

            'requested_at' =>
                $this->requested_at?->toISOString(),

            'requested_at_label' =>
                $this->requested_at
                    ?->format('j M Y h:i a'),

            'approved_at' =>
                $this->approved_at?->toISOString(),

            'approved_at_label' =>
                $this->approved_at
                    ?->format('j M Y h:i a'),

            'generated_at' =>
                $this->generated_at?->toISOString(),

            'generated_at_label' =>
                $this->generated_at
                    ?->format('j M Y h:i a'),

            'signed_at' =>
                $this->signed_at?->toISOString(),

            'signed_at_label' =>
                $this->signed_at
                    ?->format('j M Y h:i a'),

            'released_at' =>
                $this->released_at?->toISOString(),

            'released_at_label' =>
                $this->released_at
                    ?->format('j M Y h:i a'),


            /*
             * Lifecycle actors
             */

            'approved_by' =>
                $this->whenLoaded(
                    'approver',
                    fn () =>
                        $this->approver
                            ? [
                                'id' =>
                                    $this->approver->id,

                                'name' =>
                                    $this->approver->name,
                            ]
                            : null
                ),

            'generated_by' =>
                $this->whenLoaded(
                    'generator',
                    fn () =>
                        $this->generator
                            ? [
                                'id' =>
                                    $this->generator->id,

                                'name' =>
                                    $this->generator->name,
                            ]
                            : null
                ),

            'signed_by' =>
                $this->whenLoaded(
                    'signer',
                    fn () =>
                        $this->signer
                            ? [
                                'id' =>
                                    $this->signer->id,

                                'name' =>
                                    $this->signer->name,
                            ]
                            : null
                ),

            'released_by' =>
                $this->whenLoaded(
                    'releaser',
                    fn () =>
                        $this->releaser
                            ? [
                                'id' =>
                                    $this->releaser->id,

                                'name' =>
                                    $this->releaser->name,
                            ]
                            : null
                ),


            /*
             * Related data
             */

            'payments' =>
                $this->whenLoaded(
                    'payments',
                    fn () =>
                        DocumentPaymentResource::collection(
                            $this->payments
                        )
                ),

            'files' =>
                $this->whenLoaded(
                    'files',
                    fn () =>
                        DocumentFileResource::collection(
                            $this->files
                        )
                ),

            'fields' =>
                $this->whenLoaded(
                    'fields',
                    function () {

                        $fields =
                            $this->fields;

                        $barangayIds =
                            $fields
                                ->filter(
                                    fn ($field) =>
                                        $field
                                            ->documentTypeField
                                            ?->option_source ===
                                        'barangays'
                                )
                                ->pluck('value')
                                ->filter()
                                ->unique()
                                ->values();

                        $barangays =
                            $barangayIds->isEmpty()
                                ? collect()
                                : Barangay::query()
                                    ->with([
                                        'city.province',
                                    ])
                                    ->whereIn(
                                        'id',
                                        $barangayIds
                                    )
                                    ->get()
                                    ->keyBy('id');

                        return $fields
                            ->map(
                                function ($field) use (
                                    $barangays
                                ) {

                                    $value =
                                        $field->value;

                                    $fieldConfig =
                                        $field
                                            ->documentTypeField;

                                    $displayValue =
                                        $value;

                                    if (
                                        $fieldConfig
                                            ?->option_source ===
                                        'barangays'
                                    ) {
                                        $barangay =
                                            $barangays[$value] ?? null;

                                        if ($barangay) {

                                            $parts = [
                                                $barangay->barangay,
                                                $barangay->city?->city,
                                                $barangay->city?->province?->province,
                                            ];

                                            $displayValue =
                                                collect($parts)
                                                    ->filter()
                                                    ->implode(', ');
                                        }
                                    }

                                    return [
                                        'id' =>
                                            $field->id,

                                        'document_type_field_id' =>
                                            $field
                                                ->document_type_field_id,

                                        'field_key' =>
                                            $fieldConfig
                                                ?->field_key,

                                        'name' =>
                                            $fieldConfig
                                                ?->name,

                                        'value' =>
                                            $value,

                                        'display_value' =>
                                            $displayValue,
                                    ];
                                }
                            )
                            ->values();
                    }
                ),

            'attachments' =>
                DocumentRequestAttachmentResource::collection(
                    $this->whenLoaded('attachments')
                ),

            'logs' =>
                $this->whenLoaded(
                    'logs',
                    fn () =>
                        $this->logs->map(
                            function ($log) {

                                return [
                                    'status' =>
                                        $log->status?->value,

                                    'status_label' =>
                                        $log->status?->label(),

                                    'remarks' =>
                                        $log->remarks,

                                    'performed_by' =>
                                        $log->performedBy?->name,

                                    'created_at' =>
                                        $log->created_at
                                            ?->toISOString(),

                                    'created_at_label' =>
                                        $log->created_at
                                            ?->format(
                                                'j M Y h:i a'
                                            ),
                                ];
                            }
                        )
                ),


            /*
             * Model timestamps
             */

            'created_at' =>
                $this->created_at?->toISOString(),

            'created_at_label' =>
                $this->created_at
                    ?->format('j M Y h:i a'),

            'updated_at' =>
                $this->updated_at?->toISOString(),

            'updated_at_label' =>
                $this->updated_at
                    ?->format('j M Y h:i a'),
        ];
    }
}