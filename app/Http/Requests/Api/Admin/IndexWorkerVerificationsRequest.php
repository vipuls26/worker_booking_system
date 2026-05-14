<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class IndexWorkerVerificationsRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['pending', 'approved', 'rejected', 'resubmission_requested'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
