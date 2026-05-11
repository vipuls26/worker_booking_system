<?php

namespace App\Rules;

use App\Models\User;
use App\Services\Worker\WorkerScheduleService;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class WorkerBookingScheduleRule implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function __construct(private readonly WorkerScheduleService $workerSchedules) {}

    /**
     * Set all booking data so the start time can be validated with the worker, date, and end time.
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Auto-matched bookings are filtered by worker availability after candidate workers are found.
        if (empty($this->data['worker_id'])) {
            return;
        }

        // Required and date-format rules report missing or malformed booking fields.
        if (empty($this->data['booking_date']) || empty($this->data['start_time']) || empty($this->data['end_time'])) {
            return;
        }

        // Date and time format rules should own malformed input messages.
        if (! $this->hasValidDateAndTimes()) {
            return;
        }

        $worker = User::find((int) $this->data['worker_id']);

        // The exists rule owns the invalid-worker message.
        if (! $worker instanceof User) {
            return;
        }

        $availabilityError = $this->workerSchedules->bookingAvailabilityError(
            worker: $worker,
            bookingDate: (string) $this->data['booking_date'],
            startTime: (string) $this->data['start_time'],
            endTime: (string) $this->data['end_time'],
        );

        // Customers need a specific reason before the booking request reaches the service layer.
        if ($availabilityError !== null) {
            $fail($availabilityError);
        }
    }

    /**
     * Confirm the fields needed for schedule checks have already been normalized into expected formats.
     */
    private function hasValidDateAndTimes(): bool
    {
        $bookingDate = (string) $this->data['booking_date'];
        $startTime = (string) $this->data['start_time'];
        $endTime = (string) $this->data['end_time'];

        return $this->matchesFormat($bookingDate, 'Y-m-d')
            && $this->matchesFormat($startTime, 'H:i')
            && $this->matchesFormat($endTime, 'H:i');
    }

    /**
     * Check an exact date or time format without creating another validation message.
     */
    private function matchesFormat(string $value, string $format): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);

        return $date instanceof \DateTimeImmutable && $date->format($format) === $value;
    }
}
