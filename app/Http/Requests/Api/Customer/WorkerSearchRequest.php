<?php

namespace App\Http\Requests\Api\Customer;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class WorkerSearchRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')],
            'service_slug' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'max_price' => ['nullable', 'numeric', 'min:1', 'max:999999.99'],
            'min_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'available_date' => ['nullable', 'date_format:Y-m-d'],
            'available_time' => ['nullable', 'date_format:H:i'],
            'slot_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'sort' => ['nullable', Rule::in(['relevance', 'price_low', 'price_high', 'rating', 'experience'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
