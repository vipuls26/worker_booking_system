<?php

namespace App\Http\Requests\Api\Worker;

use App\Http\Requests\Api\ApiFormRequest;

class StartBookingRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * This endpoint does not need request body fields.
     * The booking itself is validated in the service layer.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
