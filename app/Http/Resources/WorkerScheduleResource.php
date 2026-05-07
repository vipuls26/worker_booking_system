<?php

namespace App\Http\Resources;

use App\Models\WorkerSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkerSchedule
 */
class WorkerScheduleResource extends JsonResource
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
            'worker_id' => $this->worker_id,
            'day_of_week' => $this->day_of_week,
            'day_name' => WorkerSchedule::Days[$this->day_of_week] ?? null,
            'start_time' => $this->start_time ? substr((string) $this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr((string) $this->end_time, 0, 5) : null,
            'is_off_day' => $this->is_off_day,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
