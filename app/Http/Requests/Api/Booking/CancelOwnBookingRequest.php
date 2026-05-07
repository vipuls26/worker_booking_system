<?php

namespace App\Http\Requests\Api\Booking;

use App\Http\Requests\Api\ApiFormRequest;

class CancelOwnBookingRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cancelled_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
