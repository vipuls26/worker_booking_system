<?php

namespace App\Http\Requests\Api\Customer;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\WorkerService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'end_time' => $this->input('end_time') ?: null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'worker_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'worker_ids' => ['nullable', 'array', 'min:1', 'max:10'],
            'worker_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')],
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'address' => ['required', 'string', 'max:1000'],
            'issue_description' => ['required', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $workerIds = collect($this->input('worker_ids', []))
                    ->push($this->input('worker_id'))
                    ->filter()
                    ->unique()
                    ->values();

                if ($workerIds->isEmpty()) {
                    $validator->errors()->add('worker_ids', 'Select at least one worker.');

                    return;
                }

                $offersService = WorkerService::query()
                    ->whereIn('worker_id', $workerIds)
                    ->where('service_id', $this->integer('service_id'))
                    ->where('is_active', true)
                    ->where('approval_status', WorkerService::StatusApproved)
                    ->whereHas('service', fn ($query) => $query->where('is_active', true))
                    ->pluck('worker_id');

                if ($offersService->count() !== $workerIds->count()) {
                    $validator->errors()->add('service_id', 'One or more selected workers do not offer this service.');
                }
            },
        ];
    }
}
