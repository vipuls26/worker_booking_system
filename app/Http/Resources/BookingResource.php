<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
class BookingResource extends JsonResource
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
            'customer' => new UserResource($this->whenLoaded('customer')),
            'worker' => new UserResource($this->whenLoaded('worker')),
            'selected_worker' => new UserResource($this->whenLoaded('selectedWorker')),
            'selected_worker_id' => $this->selected_worker_id,
            'service' => new ServiceResource($this->whenLoaded('service')),
            'booking_date' => $this->booking_date?->toDateString(),
            'booking_time' => $this->booking_time,
            'start_time' => $this->start_time ?: $this->booking_time,
            'end_time' => $this->end_time,
            'address' => $this->address,
            'notes' => $this->notes,
            'issue_description' => $this->issue_description ?: $this->notes,
            'total_amount' => $this->quoted_amount,
            'commission_rate' => $this->quoted_commission_rate,
            'platform_commission' => $this->quoted_platform_commission,
            'worker_earning' => $this->quoted_worker_earning,
            'quote' => [
                'amount' => $this->quoted_amount,
                'commission_rate' => $this->quoted_commission_rate,
                'platform_commission' => $this->quoted_platform_commission,
                'worker_earning' => $this->quoted_worker_earning,
            ],
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'paid_at' => $this->paid_at?->toISOString(),
            'latest_payment' => new PaymentResource($this->whenLoaded('latestPayment')),
            'cancelled_by' => $this->cancelled_by,
            'cancelled_by_user' => new UserResource($this->whenLoaded('cancelledBy')),
            'cancelled_reason' => $this->cancelled_reason,
            'rejection_reason' => $this->rejection_reason,
            'requests' => BookingRequestResource::collection($this->whenLoaded('bookingRequests')),
            'timeline' => BookingActivityResource::collection($this->whenLoaded('activities')),
            'review' => new ReviewResource($this->whenLoaded('review')),
            'worker_review' => new ReviewResource($this->whenLoaded('workerReview')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
