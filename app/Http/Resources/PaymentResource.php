<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
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
            'booking_id' => $this->booking_id,
            'booking' => new BookingResource($this->whenLoaded('booking')),
            'customer' => new UserResource($this->whenLoaded('customer')),
            'worker' => new UserResource($this->whenLoaded('worker')),
            'amount' => $this->amount,
            'commission_rate' => $this->commission_rate,
            'platform_commission' => $this->platform_commission,
            'worker_earning' => $this->worker_earning,
            'provider' => $this->provider,
            'transaction_reference' => $this->transaction_reference,
            'status' => $this->status,
            'paid_at' => $this->paid_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
