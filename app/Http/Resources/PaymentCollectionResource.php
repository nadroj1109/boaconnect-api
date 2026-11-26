<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentCollectionResource extends JsonResource
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

            'amount' => $this->amount,

            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),

            'provider' => $this->provider?->value,
            'provider_label' => $this->provider?->label(),

            'payment_method' => $this->payment_method?->value,
            'payment_method_label' => $this->payment_method?->label(),

            'reference_no' => $this->reference_no,
            'provider_reference' => $this->provider_reference,
            'blockchain_anchors' => $this->whenLoaded(
                'blockchainAnchors',
                fn () => $this->blockchainAnchors->map(fn ($anchor) => [
                    'id' => $anchor->id,
                    'uuid' => $anchor->uuid,
                    'proof_type' => $anchor->proof_type,
                    'proof_hash' => $anchor->proof_hash,
                    'transaction_hash' => $anchor->transaction_hash,
                    'network' => $anchor->network,
                    'chain_id' => $anchor->chain_id,
                    'contract_address' => $anchor->contract_address,
                    'block_number' => $anchor->block_number,
                    'status' => $anchor->status,
                    'submitted_at' => $anchor->submitted_at?->toISOString(),
                    'confirmed_at' => $anchor->confirmed_at?->toISOString(),
                ])->values()
            ),

            'payment_date' => (
                $this->paid_at ?? $this->created_at
            )?->toISOString(),

            'paid_at' => $this->paid_at?->toISOString(),

            'created_at' => $this->created_at?->toISOString(),

            'received_by' => $this->receivedBy
                ? [
                    'id' => $this->receivedBy->id,
                    'name' => $this->receivedBy->name,
                ]
                : null,

            'document_request' => $this->whenLoaded(
                'documentRequest',
                function () {
                    $documentRequest = $this->documentRequest;

                    if (! $documentRequest) {
                        return null;
                    }

                    $resident = $documentRequest->resident;
                    $requestedFor = $documentRequest->requestedFor;
                    $documentType = $documentRequest->documentType;

                    $isDifferentResident =
                        $documentRequest->requested_for_resident_id !==
                        $documentRequest->resident_id;

                    return [
                        'id' => $documentRequest->id,
                        'request_no' => $documentRequest->request_no,

                        'resident' => $resident
                            ? [
                                'id' => $resident->id,
                                'full_name' => $resident->full_name,
                                'mobile_no' => $resident->mobile_no,
                            ]
                            : null,

                        'requested_for' => (
                            $isDifferentResident && $requestedFor
                        )
                            ? [
                                'id' => $requestedFor->id,
                                'full_name' => $requestedFor->full_name,
                            ]
                            : null,

                        'document_type' => $documentType
                            ? [
                                'id' => $documentType->id,
                                'name' => $documentType->name,
                            ]
                            : null,

                        'created_at' => $this->documentRequest?->created_at,
                    ];
                }
            ),
        ];
    }
}
