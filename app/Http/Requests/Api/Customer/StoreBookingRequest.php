<?php

namespace App\Http\Requests\Api\Customer;

use App\Http\Requests\Api\ApiFormRequest;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $durationMinutes = (int) $this->input('duration_minutes', 60);
        $endTime = $this->input('end_time');

        if (! $endTime && $this->filled(['booking_date', 'start_time']) && $durationMinutes > 0) {
            $endTime = CarbonImmutable::parse($this->input('booking_date').' '.$this->input('start_time'))
                ->addMinutes($durationMinutes)
                ->format('H:i');
        }

        $this->merge([
            'duration_minutes' => $durationMinutes,
            'end_time' => $endTime ?: null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'worker_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:60', 'max:480'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'address' => ['nullable', 'string', 'max:1000'],
            'issue_description' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
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
