<?php

namespace App\Http\Requests\Api\Worker;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateBookingStatusRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['accepted', 'rejected', 'in_progress', 'completed', 'cancelled'])],
            'rejection_reason' => ['nullable', 'required_if:status,rejected', 'string', 'max:1000'],
            'cancelled_reason' => ['nullable', 'required_if:status,cancelled', 'string', 'max:1000'],
        ];
    }
}
