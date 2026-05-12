<?php

namespace App\Http\Requests\Api\Customer;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestWorker;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

class RescheduleBookingRequest extends ApiFormRequest
{
    /**
     * Build the requested booking start time so same-day reschedules cannot move into the past.
     */
    private function requestedStartDateTime(): ?CarbonImmutable
    {
        if (! $this->filled(['booking_date', 'start_time'])) {
            return null;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $this->input('booking_date')) || ! preg_match('/^\d{2}:\d{2}$/', (string) $this->input('start_time'))) {
            return null;
        }

        return CarbonImmutable::parse($this->input('booking_date').' '.$this->input('start_time'));
    }

    protected function prepareForValidation(): void
    {
        $normalizedStartTime = $this->normalizeTime((string) $this->input('start_time'));
        $normalizedEndTime = $this->normalizeTime((string) $this->input('end_time'));
        $durationMinutes = (int) $this->input('duration_minutes', 60);
        $endTime = $normalizedEndTime;

        if (! $endTime && $this->filled('booking_date') && $normalizedStartTime && $durationMinutes > 0) {
            $endTime = CarbonImmutable::parse($this->input('booking_date').' '.$normalizedStartTime)
                ->addMinutes($durationMinutes)
                ->format('H:i');
        }

        if ($this->filled('booking_date') && $normalizedStartTime && $endTime) {
            $durationMinutes = CarbonImmutable::parse($this->input('booking_date').' '.$normalizedStartTime)
                ->diffInMinutes(CarbonImmutable::parse($this->input('booking_date').' '.$endTime));
        }

        $this->merge([
            'start_time' => $normalizedStartTime ?: $this->input('start_time'),
            'duration_minutes' => $durationMinutes,
            'end_time' => $endTime ?: null,
            'worker_id' => $this->assignedWorkerId(),
        ]);
    }

    /**
     * Accept both HH:MM and HH:MM:SS from the frontend and store them as HH:MM.
     */
    private function normalizeTime(string $time): ?string
    {
        if ($time === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time) === 1) {
            return substr($time, 0, 5);
        }

        return $time;
    }

    /**
     * Read the worker from the awaiting-reschedule invitation tied to this request.
     */
    private function assignedWorkerId(): ?int
    {
        $booking = $this->route('booking');

        if (! $booking instanceof ServiceRequest) {
            return null;
        }

        return $booking->workers()
            ->where('status', ServiceRequestWorker::STATUS_AWAITING_RESCHEDULE)
            ->value('worker_id');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:60', 'max:480'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $requestedStartDateTime = $this->requestedStartDateTime();

                // Customers should not be able to reschedule into a time slot that already passed.
                if ($requestedStartDateTime !== null && $requestedStartDateTime->lessThan(CarbonImmutable::now())) {
                    $validator->errors()->add('start_time', 'Please choose the current time or a future time.');
                }
            },
        ];
    }
}
