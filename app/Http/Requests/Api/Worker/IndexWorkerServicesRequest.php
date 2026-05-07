<?php

namespace App\Http\Requests\Api\Worker;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\WorkerService;
use Illuminate\Validation\Rule;

class IndexWorkerServicesRequest extends ApiFormRequest
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
            'pricing_type' => ['nullable', Rule::in([WorkerService::PricingFixed, WorkerService::PricingHourly])],
            'is_active' => ['nullable', 'boolean'],
            'approval_status' => ['nullable', Rule::in([WorkerService::StatusPending, WorkerService::StatusApproved, WorkerService::StatusRejected])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
