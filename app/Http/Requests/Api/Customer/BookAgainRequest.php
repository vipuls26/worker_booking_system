<?php

namespace App\Http\Requests\Api\Customer;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\ServiceRequest;

class BookAgainRequest extends ApiFormRequest
{
    /**
     * Confirm this customer owns a completed booking behind the requested service request.
     */
    public function authorize(): bool
    {
        $serviceRequest = $this->route('booking');

        // Route model binding should provide the service request before authorization runs.
        if (! $serviceRequest instanceof ServiceRequest) {
            return false;
        }

        $booking = $serviceRequest->loadMissing('booking')->booking;

        // Only the customer who owns the completed booking can ask for rebooking defaults.
        if (! $booking) {
            return false;
        }

        return $this->user()?->can('bookAgain', $booking) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Book again has no customer input; it returns safe defaults for the booking form.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
