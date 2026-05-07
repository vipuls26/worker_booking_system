<?php

namespace App\Services\Worker;

use App\Models\User;
use App\Models\WorkerSchedule;
use Illuminate\Database\Eloquent\Collection;

class WorkerScheduleService
{
    /**
     * @return Collection<int, WorkerSchedule>
     */
    public function weeklySchedule(User $worker): Collection
    {
        return $worker->workerSchedules()
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * @param  array{day_of_week: int, start_time?: string|null, end_time?: string|null, is_off_day: bool}  $data
     */
    public function create(User $worker, array $data): WorkerSchedule
    {
        return $worker->workerSchedules()->create($data);
    }

    /**
     * @param  array{day_of_week: int, start_time?: string|null, end_time?: string|null, is_off_day: bool}  $data
     */
    public function update(WorkerSchedule $schedule, array $data): WorkerSchedule
    {
        $schedule->update($data);

        return $schedule->refresh();
    }

    public function delete(WorkerSchedule $schedule): void
    {
        $schedule->delete();
    }

    /**
     * @param  array{day_of_week: int, start_time?: string|null, end_time?: string|null, is_off_day: bool}  $data
     */
    public function overlaps(User $worker, array $data, ?int $ignoreId = null): bool
    {
        if ($data['is_off_day'] || empty($data['start_time']) || empty($data['end_time'])) {
            return false;
        }

        return $worker->workerSchedules()
            ->where('day_of_week', $data['day_of_week'])
            ->where('is_off_day', false)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->exists();
    }

    /**
     * @param  array{day_of_week: int, start_time?: string|null, end_time?: string|null, is_off_day: bool}  $data
     */
    public function conflictsWithDayMode(User $worker, array $data, ?int $ignoreId = null): ?string
    {
        $query = $worker->workerSchedules()
            ->where('day_of_week', $data['day_of_week'])
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId));

        if ($data['is_off_day'] && (clone $query)->exists()) {
            return 'Remove working windows before marking this day as off.';
        }

        if (! $data['is_off_day'] && (clone $query)->where('is_off_day', true)->exists()) {
            return 'This day is marked as off. Remove the off-day entry before adding working windows.';
        }

        return null;
    }
}
