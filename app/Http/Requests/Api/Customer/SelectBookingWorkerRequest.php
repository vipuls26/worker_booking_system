<?php

namespace App\Http\Requests\Api\Customer;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class SelectBookingWorkerRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'booking_request_id' => ['required', 'integer', Rule::exists('booking_requests', 'id')],
        ];
    }
}
