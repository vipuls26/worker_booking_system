<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\WorkerService;
use Illuminate\Validation\Rule;

class IndexWorkerServiceApprovalsRequest extends ApiFormRequest
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
            'status' => ['nullable', Rule::in([WorkerService::StatusPending, WorkerService::StatusApproved, WorkerService::StatusRejected])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
