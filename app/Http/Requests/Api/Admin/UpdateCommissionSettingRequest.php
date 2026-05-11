<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateCommissionSettingRequest extends ApiFormRequest
{
    /**
     * Normalize the submitted percentage before validation runs.
     */
    protected function prepareForValidation(): void
    {
        // Empty form values must remain invalid instead of being silently converted to zero.
        if ($this->input('commission_rate') === '') {
            return;
        }

        $this->merge([
            'commission_rate' => $this->input('commission_rate'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
