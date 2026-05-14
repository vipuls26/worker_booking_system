<?php

namespace App\Http\Requests\Api\Customer;

use App\Http\Requests\Api\ApiFormRequest;
use App\Rules\WorkerBookingScheduleRule;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends ApiFormRequest
{
    /**
     * Build the requested booking start time in application time so same-day requests can be checked safely.
     */
    private function requestedStartDateTime(): ?CarbonImmutable
    {
        if (! $this->filled(['booking_date', 'start_time'])) {
            return null;
        }

        // Invalid formats should be handled by the validator without throwing here.
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
            'recreated_from_booking_id' => $this->input('recreated_from_booking_id') ?: $this->input('source_booking_id'),
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $latestBookingDate = CarbonImmutable::today()->addMonthNoOverflow()->toDateString();

        return [
            'worker_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.$latestBookingDate],
            'start_time' => ['required', 'date_format:H:i', app(WorkerBookingScheduleRule::class)],
            'duration_minutes' => ['required', 'integer', 'min:60', 'max:480'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'address' => ['nullable', 'string', 'max:1000'],
            'issue_description' => ['required', 'string', 'max:2000'],
            'recreated_from_booking_id' => ['nullable', 'integer', Rule::exists('bookings', 'id')],
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

                // Customers should not be able to request a slot that has already started.
                if ($requestedStartDateTime !== null && $requestedStartDateTime->lessThan(CarbonImmutable::now())) {
                    $validator->errors()->add('start_time', 'Please choose the current time or a future time.');
                }

                // A service address is required before a worker can be dispatched.
                if ($this->filled('address')) {
                    return;
                }

                if ($this->user()?->customerProfile?->address) {
                    return;
                }

                $validator->errors()->add('address', 'Add a service address or save a default address in your profile.');
            },
        ];
    }
}
