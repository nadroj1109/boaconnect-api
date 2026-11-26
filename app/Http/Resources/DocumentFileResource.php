<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentFileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,

            'filename' => $this->filename,

            'mime_type' => $this->mime_type,

            'size' => $this->size,

            'extension' => $this->extension(),

            /*
             * This is an API endpoint, NOT a direct storage URL.
             *
             * The endpoint will perform authorization before
             * accessing the private file.
             */
            'view_url' => route(
                'document-files.view',
                $this->uuid
            ),

            'download_url' => route(
                'document-files.download',
                $this->uuid
            ),

            'created_at' =>
                $this->created_at?->toISOString(),

            'blockchain_anchors' => $this->whenLoaded(
                'blockchainAnchors',
                fn () => $this->blockchainAnchors->map(
                    fn ($anchor) => [
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
                    ]
                )->values()
            ),
        ];
    }
}
