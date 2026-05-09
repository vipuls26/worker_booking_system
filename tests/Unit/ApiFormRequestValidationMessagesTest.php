<?php

namespace Tests\Unit;

use App\Http\Requests\Api\ApiFormRequest;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Requests\Api\Booking\CancelOwnBookingRequest;
use App\Http\Requests\Api\Customer\StoreBookingRequest;
use App\Http\Requests\Api\Worker\StoreWorkerScheduleRequest;
use PHPUnit\Framework\TestCase;

class ApiFormRequestValidationMessagesTest extends TestCase
{
    public function test_auth_requests_use_the_shared_api_form_request_base(): void
    {
        $this->assertInstanceOf(ApiFormRequest::class, new LoginRequest);
        $this->assertInstanceOf(ApiFormRequest::class, new RegisterRequest);
    }

    public function test_shared_custom_validation_messages_are_available_to_api_requests(): void
    {
        $messages = (new StoreWorkerScheduleRequest)->messages();

        $this->assertSame('Please choose an account type.', $messages['role_id.required']);
        $this->assertSame('Please provide a reason.', $messages['reason.required']);
        $this->assertSame('Please choose a valid status.', $messages['status.in']);
        $this->assertSame('Please choose a booking date.', $messages['booking_date.required']);
        $this->assertSame(
            'Please provide a start time unless this is marked as an off day.',
            $messages['start_time.required_unless'],
        );
        $this->assertSame('The cancellation reason may not be greater than :max characters.', $messages['cancelled_reason.max']);
        $this->assertSame('Please choose fixed or hourly pricing.', $messages['pricing_type.in']);
        $this->assertSame('Please upload ID proof.', $messages['id_proof.required']);
        $this->assertSame('Please enter a valid email address.', $messages['email']);
    }

    public function test_shared_validation_attributes_are_readable(): void
    {
        $attributes = (new RegisterRequest)->attributes();

        $this->assertSame('account type', $attributes['role_id']);
        $this->assertSame('phone number', $attributes['phone']);
        $this->assertSame('start time', $attributes['start_time']);
        $this->assertSame('booking duration', $attributes['duration_minutes']);
    }

    public function test_domain_requests_receive_the_shared_custom_messages(): void
    {
        $bookingMessages = (new CancelOwnBookingRequest)->messages();
        $customerMessages = (new StoreBookingRequest)->messages();

        $this->assertSame('The cancellation reason may not be greater than :max characters.', $bookingMessages['cancelled_reason.max']);
        $this->assertSame('Please describe the issue.', $customerMessages['issue_description.required']);
        $this->assertSame('The selected service is invalid or inactive.', $customerMessages['service_id.exists']);
    }
}
