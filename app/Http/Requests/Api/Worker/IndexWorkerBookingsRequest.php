<?php

namespace App\Http\Requests\Api\Worker;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class IndexWorkerBookingsRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['open', 'worker_selected', 'pending', 'confirmed', 'accepted', 'rejected', 'expired', 'selected', 'not_selected', 'in_progress', 'completed', 'cancelled'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
