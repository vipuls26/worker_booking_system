<?php

namespace App\Http\Requests\Api\Worker;

use App\Http\Requests\Api\ApiFormRequest;
use App\Services\Worker\WorkerScheduleService;
use Illuminate\Validation\Validator;

class StoreWorkerScheduleRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->boolean('is_off_day')) {
            $this->merge([
                'start_time' => null,
                'end_time' => null,
            ]);
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
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['nullable', 'required_unless:is_off_day,true,1', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_unless:is_off_day,true,1', 'date_format:H:i', 'after:start_time'],
            'is_off_day' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $schedules = app(WorkerScheduleService::class);
                $conflict = $schedules->conflictsWithDayMode($this->user(), $this->validated());

                if ($conflict) {
                    $validator->errors()->add('day_of_week', $conflict);

                    return;
                }

                if ($schedules->overlaps($this->user(), $this->validated())) {
                    $validator->errors()->add('start_time', 'This schedule overlaps an existing working time.');
                }
            },
        ];
    }
}
