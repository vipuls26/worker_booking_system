<?php

namespace App\Http\Requests\Api\Customer;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'end_time' => $this->input('end_time') ?: null,
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
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'address' => ['required', 'string', 'max:1000'],
            'issue_description' => ['required', 'string', 'max:2000'],
        ];
    }
}
