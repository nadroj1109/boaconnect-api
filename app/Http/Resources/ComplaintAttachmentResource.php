<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintAttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' =>
                $this->uuid,

            'filename' =>
                $this->filename,

            'mime_type' =>
                $this->mime_type,

            'size' =>
                $this->size,

            'uploaded_by' =>
                $this->whenLoaded(
                    'uploadedBy',
                    fn () => $this->uploadedBy
                        ? [
                            'id' =>
                                $this->uploadedBy->id,

                            'name' =>
                                $this->uploadedBy->name,
                        ]
                        : null
                ),

            'created_at' =>
                $this->created_at?->toISOString(),

            'created_at_label' =>
                $this->created_at
                    ?->format('j M Y h:i a'),
        ];
    }
}
