<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentRequestAttachmentResource extends JsonResource
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

            'requirement' =>
                $this->whenLoaded(
                    'requirement',
                    fn () => $this->requirement
                        ? [
                            'id' =>
                                $this->requirement->id,

                            'name' =>
                                $this->requirement->name,

                            'description' =>
                                $this->requirement->description,

                            'is_required' =>
                                $this->requirement->is_required,
                        ]
                        : null
                ),

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
