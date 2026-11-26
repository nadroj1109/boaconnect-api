<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentPaymentResource extends JsonResource
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

            'status' =>
                $this->status?->value,

            'status_label' =>
                $this->status?->label(),

            'provider' =>
                $this->provider?->value,

            'payment_method' =>
                $this->payment_method?->value,

            'payment_method_label' =>
                $this->payment_method?->label(),

            'reference_no' =>
                $this->reference_no,

            'provider_reference' =>
                $this->provider_reference,

            'checkout_session_id' =>
                $this->checkout_session_id,

            /*
             * This is safe to expose.
             * It is the URL the resident needs
             * to open the PayMongo hosted checkout.
             */
            'checkout_url' =>
                $this->checkout_url,

            'received_by' => $this->whenLoaded(
                'receivedBy',
                fn () => $this->receivedBy
                    ? [
                        'id' => $this->receivedBy->id,
                        'name' => $this->receivedBy->name,
                    ]
                    : null,
                ),

            'paid_at' =>
                $this->paid_at?->toISOString(),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}
