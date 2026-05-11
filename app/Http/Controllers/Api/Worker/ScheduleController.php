<?php

namespace App\Http\Controllers\Api\Worker;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Worker\StoreWorkerScheduleRequest;
use App\Http\Requests\Api\Worker\UpdateWorkerScheduleRequest;
use App\Http\Requests\Api\Worker\WorkerAvailabilityRequest;
use App\Http\Resources\WorkerScheduleResource;
use App\Models\WorkerSchedule;
use App\Services\Worker\AvailabilityCheckerService;
use App\Services\Worker\WorkerScheduleService;
use Illuminate\Http\JsonResponse;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly WorkerScheduleService $schedules,
        private readonly AvailabilityCheckerService $availability,
    ) {}

    public function index(): JsonResponse
    {
        // Workers manage weekly availability windows for booking matching.
        return response()->json([
            'success' => true,
            'message' => 'Worker schedules retrieved',
            'data' => [
                'schedules' => WorkerScheduleResource::collection($this->schedules->weeklySchedule(request()->user())),
            ],
        ]);
    }

    public function store(StoreWorkerScheduleRequest $request): JsonResponse
    {
        // New schedule windows immediately affect worker availability checks.
        $schedule = $this->schedules->create($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Worker schedule created',
            'data' => [
                'schedule' => new WorkerScheduleResource($schedule),
            ],
        ], 201);
    }

    public function update(UpdateWorkerScheduleRequest $request, WorkerSchedule $workerSchedule): JsonResponse
    {
        // Workers can update only their own availability records.
        $this->ensureOwnedByWorker($workerSchedule);

        $schedule = $this->schedules->update($workerSchedule, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Worker schedule updated',
            'data' => [
                'schedule' => new WorkerScheduleResource($schedule),
            ],
        ]);
    }

    public function destroy(WorkerSchedule $workerSchedule): JsonResponse
    {
        // Deleting a schedule removes that availability window from future matching.
        $this->ensureOwnedByWorker($workerSchedule);

        $this->schedules->delete($workerSchedule);

        return response()->json([
            'success' => true,
            'message' => 'Worker schedule deleted',
            'data' => [],
        ]);
    }

    public function availability(WorkerAvailabilityRequest $request): JsonResponse
    {
        // Availability previews show workers how their schedule appears for a specific date.
        $slots = $this->availability->slotsForDate(
            $request->user(),
            $request->validated('date'),
            (int) ($request->validated('slot_minutes') ?? 60),
        );

        return response()->json([
            'success' => true,
            'message' => 'Worker availability retrieved',
            'data' => [
                'date' => $request->validated('date'),
                'slots' => $slots,
            ],
        ]);
    }

    private function ensureOwnedByWorker(WorkerSchedule $workerSchedule): void
    {
        // Missing and cross-worker schedule IDs both return 404 to avoid leaking records.
        abort_if($workerSchedule->worker_id !== request()->user()?->id, 404);
    }
}
