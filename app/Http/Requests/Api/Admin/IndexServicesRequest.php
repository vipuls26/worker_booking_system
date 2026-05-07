<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiFormRequest;

class IndexServicesRequest extends ApiFormRequest
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
            'search' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
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
