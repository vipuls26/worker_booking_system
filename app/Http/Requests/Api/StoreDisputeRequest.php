<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class StoreDisputeRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'booking_id' => ['required', 'integer', Rule::exists('bookings', 'id')],
            'category' => ['required', 'string', Rule::in(['service_issue', 'payment_issue', 'worker_no_show', 'customer_issue', 'other'])],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
