<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\UnblockRequest;
use Illuminate\Validation\Rule;

class IndexUnblockRequestsRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in([
                UnblockRequest::STATUS_PENDING,
                UnblockRequest::STATUS_APPROVED,
                UnblockRequest::STATUS_REJECTED,
            ])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
