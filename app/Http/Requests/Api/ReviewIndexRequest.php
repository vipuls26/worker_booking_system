<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class ReviewIndexRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'sort' => ['nullable', Rule::in(['latest', 'rating_high', 'rating_low'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
