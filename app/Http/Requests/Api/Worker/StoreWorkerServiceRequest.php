<?php

namespace App\Http\Requests\Api\Worker;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\WorkerService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWorkerServiceRequest extends ApiFormRequest
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
        return [
            'service_id' => [
                'required',
                'integer',
                Rule::exists('services', 'id')->where('is_active', true),
            ],
            'pricing_type' => ['required', Rule::in(['fixed', 'hourly'])],
            'price' => ['required', 'numeric', 'min:1', 'max:999999.99'],
            'minimum_hours' => ['nullable', 'required_if:pricing_type,hourly', 'integer', 'min:1', 'max:24'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Add the worker service reapplication business rule after basic field validation passes.
     *
     * Rejected applications may be resubmitted, but pending or approved applications are still duplicates.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                // Skip duplicate checks when the request is already invalid.
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                // Check whether the worker already has a non-rejected application for this service.
                $existingApplication = WorkerService::query()
                    ->where('worker_id', $this->user()?->id)
                    ->where('service_id', $this->integer('service_id'))
                    ->first();

                // Workers can only reapply when the previous application was rejected.
                if ($existingApplication instanceof WorkerService && $existingApplication->approval_status !== WorkerService::StatusRejected) {
                    $validator->errors()->add('service_id', 'This service has already been submitted and is not eligible for reapplication.');
                }
            },
        ];
    }
}
