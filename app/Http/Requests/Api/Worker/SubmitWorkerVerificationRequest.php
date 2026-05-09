<?php

namespace App\Http\Requests\Api\Worker;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rules\File;

class SubmitWorkerVerificationRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $hasExistingIdProof = $this->user()
            ?->workerVerification()
            ->whereNotNull('id_proof')
            ->exists() ?? false;

        return [
            'id_proof' => [$hasExistingIdProof ? 'sometimes' : 'required', File::types(['jpg', 'jpeg', 'png', 'pdf'])->max('5mb')],
            'certificates' => ['sometimes', 'array', 'max:5'],
            'certificates.*' => [File::types(['jpg', 'jpeg', 'png', 'pdf'])->max('5mb')],
            'experience_years' => ['required', 'integer', 'min:0', 'max:60'],
            'mobile_verified' => ['sometimes', 'boolean'],
        ];
    }
}
