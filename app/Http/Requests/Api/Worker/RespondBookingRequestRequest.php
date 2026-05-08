<?php

namespace App\Http\Requests\Api\Worker;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class RespondBookingRequestRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['accepted', 'rejected', 'cancelled'])],
            'response_reason' => ['nullable', 'required_if:status,cancelled', 'string', 'max:1000'],
        ];
    }
}
