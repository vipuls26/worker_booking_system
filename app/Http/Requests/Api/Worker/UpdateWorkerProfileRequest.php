<?php

namespace App\Http\Requests\Api\Worker;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateWorkerProfileRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'skills' => $this->input('skills') === '' ? [] : $this->input('skills'),
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
            'profile_photo' => ['nullable', File::image()->max('3mb')],
            'bio' => ['nullable', 'string', 'max:2000'],
            'experience_years' => ['required', 'integer', 'min:0', 'max:60'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:120'],
            'skills' => ['nullable', 'array', 'max:30'],
            'skills.*' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($this->user()?->id)],
        ];
    }
}
