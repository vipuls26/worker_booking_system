<?php

namespace App\Http\Requests\Api\Worker;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkerServiceRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('pricing_type') === 'fixed') {
            $this->merge(['minimum_hours' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $workerService = $this->route('workerService');

        return [
            'service_id' => [
                'required',
                'integer',
                Rule::exists('services', 'id')->where('is_active', true),
                Rule::unique('worker_services', 'service_id')
                    ->where('worker_id', $this->user()?->id)
                    ->ignore($workerService?->id),
            ],
            'pricing_type' => ['required', Rule::in(['fixed', 'hourly'])],
            'price' => ['required', 'numeric', 'min:1', 'max:999999.99'],
            'minimum_hours' => ['nullable', 'required_if:pricing_type,hourly', 'integer', 'min:1', 'max:24'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
