<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

class BlockUserRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'block_type' => ['required', 'string', Rule::in([
                User::STATUS_PARTIALLY_BLOCKED,
                User::STATUS_FULLY_BLOCKED,
            ])],
        ];
    }
}
