<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiFormRequest;

class DeleteServiceRequest extends ApiFormRequest
{
    /**
     * Normalize the force flag so admins can pass it from query params or JSON.
     */
    protected function prepareForValidation(): void
    {
        $this->normalizeBoolean('force');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'force' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Convert supported boolean-like request values to real booleans before validation.
     */
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
