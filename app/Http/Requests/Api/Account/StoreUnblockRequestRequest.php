<?php

namespace App\Http\Requests\Api\Account;

use App\Http\Requests\Api\ApiFormRequest;

class StoreUnblockRequestRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
