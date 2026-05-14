<?php

namespace App\Http\Requests\Api\Worker;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\WorkerService;
use Illuminate\Validation\Rule;

class IndexWorkerServicesRequest extends ApiFormRequest
{
    /**
     * Normalize optional filter values so blank query strings do not fail boolean validation.
     */
    protected function prepareForValidation(): void
    {
        $this->normalizeBooleanFilter('is_active');
    }

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

    /**
     * Normalize worker list boolean filters so blank or "all" values act like no filter.
     */
    private function normalizeBooleanFilter(string $key): void
    {
        if (! $this->has($key)) {
            return;
        }

        $value = $this->input($key);

        // Filter dropdowns may send an empty or "all" sentinel value for the unfiltered state.
        if ($value === '' || $value === 'all' || $value === null) {
            $this->merge([$key => null]);

            return;
        }

        $normalizedValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        // Invalid boolean-like filter values should fall back to the unfiltered state.
        $this->merge([$key => $normalizedValue]);
    }
}
