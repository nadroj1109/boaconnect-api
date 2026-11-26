<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResidentComplaintHistoryResource extends JsonResource
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

            'complaint_no' =>
                $this->complaint_no,

            'complaint_type' =>
                $this->whenLoaded(
                    'complaintType',
                    fn () => [
                        'id' =>
                            $this->complaintType->id,

                        'name' =>
                            $this->complaintType->name,
                    ]
                ),

            'subject' =>
                $this->subject,

            'incident_date' =>
                $this->incident_date
                    ?->toDateString(),

            'incident_date_label' =>
                $this->incident_date
                    ?->format('j M Y'),

            'status' =>
                $this->status?->value,

            'status_label' =>
                $this->status?->label(),

            'resolution' =>
                $this->resolution,

            'resolved_at' =>
                $this->resolved_at
                    ?->toISOString(),

            'resolved_at_label' =>
                $this->resolved_at
                    ?->format('j M Y h:i a'),

            'closed_at' =>
                $this->closed_at
                    ?->toISOString(),

            'closed_at_label' =>
                $this->closed_at
                    ?->format('j M Y h:i a'),

            'is_historical' =>
                $this->is_historical,
        ];
    }
}
