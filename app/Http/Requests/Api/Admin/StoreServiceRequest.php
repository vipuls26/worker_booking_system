<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiFormRequest;

class StoreServiceRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->normalizeBoolean('is_active');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:services,name'],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function normalizeBoolean(string $key): void
    {
        if (! $this->has($key)) {
            return;
        }

        $value = filter_var($this->input($key), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($value !== null) {
            $this->merge([$key => $value]);
        }
    }
}
