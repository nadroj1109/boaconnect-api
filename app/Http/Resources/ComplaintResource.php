<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintResource extends JsonResource
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

            'complaint_no' => $this->complaint_no,
            'resident_id' => $this->resident_id,
            'respondent_id' => $this->respondent_id,
            'complaint_type_id' => $this->complaint_type_id,

            'barangay' =>
                $this->whenLoaded(
                    'barangay',
                    fn () => [
                        'id' =>
                            $this->barangay->id,

                        'name' =>
                            $this->barangay->name,
                    ]
                ),

            'resident' =>
                $this->whenLoaded(
                    'resident',
                    fn () => [
                        'id' =>
                            $this->resident->id,

                        'name' =>
                            $this->resident->full_name,
                    ]
                ),

            'respondent' =>
                $this->whenLoaded(
                    'respondent',
                    fn () => [
                        'id' =>
                            $this->respondent->id,

                        'name' =>
                            $this->respondent->full_name,
                    ]
                ),

            'submitted_by' =>
                $this->whenLoaded(
                    'submittedBy',
                    fn () => [
                        'id' =>
                            $this->submittedBy->id,

                        'name' =>
                            $this->submittedBy->name,
                    ]
                ),

            'source' =>
                $this->source?->value,

            'source_label' =>
                $this->source?->label(),

            'complaint_type' =>
                $this->whenLoaded(
                    'complaintType',
                    fn () => [
                        'id' =>
                            $this->complaintType->id,

                        'name' =>
                            $this->complaintType->name,

                        'description' =>
                            $this->complaintType->description,
                    ]
                ),

            'subject' =>
                $this->subject,

            'description' =>
                $this->description,

            'incident_date' =>
                $this->incident_date?->toDateString(),

            'incident_date_label' =>
                $this->incident_date?->format('j M Y'),

            'incident_time' =>
                $this->incident_time,

            'incident_location' =>
                $this->incident_location,

            'status' =>
                $this->status?->value,

            'status_label' =>
                $this->status?->label(),

            'assigned_to' =>
                $this->whenLoaded(
                    'assignedTo',
                    fn () => $this->assignedTo
                        ? [
                            'id' =>
                                $this->assignedTo->id,

                            'name' =>
                                $this->assignedTo->name,
                        ]
                        : null
                ),

            'resolution' =>
                $this->resolution,

            'resolved_at' =>
                $this->resolved_at?->toISOString(),

            'resolved_at_label' =>
                $this->resolved_at
                    ?->format('j M Y h:i a'),

            'closed_at' =>
                $this->closed_at?->toISOString(),

            'closed_at_label' =>
                $this->closed_at
                    ?->format('j M Y h:i a'),

            'logs' =>
                $this->whenLoaded(
                    'logs',
                    fn () => $this->logs->map(
                        function ($log) {
                            return [
                                'id' =>
                                    $log->id,

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
                                        ?->format('j M Y h:i a'),
                            ];
                        }
                    )
                ),

            'hearings' => $this->whenLoaded(
                'hearings',
                fn () => $this->hearings->map(
                    function ($hearing) {
                        return [
                            'id' => $hearing->id,

                            'hearing_date' =>
                                $hearing->hearing_date
                                    ?->toDateString(),

                            'hearing_date_label' =>
                                $hearing->hearing_date
                                    ?->format('j M Y'),

                            'hearing_time' =>
                                $hearing->hearing_time,

                            'location' =>
                                $hearing->location,

                            'status' =>
                                $hearing->status?->value,

                            'status_label' =>
                                $hearing->status?->label(),

                            'is_final' =>
                                $hearing->status?->isFinal() ?? false,

                            'remarks' =>
                                $hearing->remarks,

                            'scheduled_by' =>
                                $hearing->scheduledBy?->name,

                            'created_at' =>
                                $hearing->created_at
                                    ?->toISOString(),

                            'created_at_label' =>
                                $hearing->created_at
                                    ?->format('j M Y h:i a'),
                        ];
                    }
                )
            ),

            'attachments' => $this->whenLoaded(
                'attachments',
                fn () => $this->attachments->map(
                    function ($attachment) {
                        return [
                            'uuid' =>
                                $attachment->uuid,

                            'filename' =>
                                $attachment->filename,

                            'mime_type' =>
                                $attachment->mime_type,

                            'size' =>
                                $attachment->size,

                            'uploaded_by' =>
                                $attachment->uploadedBy?->name,

                            'created_at' =>
                                $attachment->created_at
                                    ?->toISOString(),

                            'created_at_label' =>
                                $attachment->created_at
                                    ?->format('j M Y h:i a'),
                        ];
                    }
                )
            ),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),

            'created_at_label' =>
                $this->created_at
                    ?->format('j M Y h:i a'),

            'updated_at_label' =>
                $this->updated_at
                    ?->format('j M Y h:i a'),
        ];
    }
}
