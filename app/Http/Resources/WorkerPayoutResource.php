<?php

namespace App\Http\Resources;

use App\Models\WorkerPayout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkerPayout
 */
class WorkerPayoutResource extends JsonResource
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
            'worker' => new UserResource($this->whenLoaded('worker')),
            'processor' => new UserResource($this->whenLoaded('processor')),
            'amount' => $this->amount,
            'status' => $this->status,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'reference' => $this->reference,
            'notes' => $this->notes,
            'paid_at' => $this->paid_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
