<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\Dispute;
use Illuminate\Validation\Rule;

class IndexDisputesRequest extends ApiFormRequest
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
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in([Dispute::STATUS_OPEN, Dispute::STATUS_UNDER_REVIEW, Dispute::STATUS_RESOLVED, Dispute::STATUS_REJECTED])],
            'category' => ['nullable', 'string', 'max:80'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ];
    }
}
