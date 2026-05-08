<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\Dispute;
use Illuminate\Validation\Rule;

class ResolveDisputeRequest extends ApiFormRequest
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
            'status' => ['required', Rule::in([Dispute::STATUS_UNDER_REVIEW, Dispute::STATUS_RESOLVED, Dispute::STATUS_REJECTED])],
            'resolution_note' => ['required_if:status,'.Dispute::STATUS_RESOLVED.','.Dispute::STATUS_REJECTED, 'nullable', 'string', 'max:5000'],
        ];
    }
}
