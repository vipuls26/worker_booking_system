<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\User;
use App\Models\WorkerPayout;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkerPayoutService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return Collection<int, WorkerPayout>
     */
    public function processWeeklyPayouts(CarbonInterface $periodStart, CarbonInterface $periodEnd): Collection
    {
        $payouts = new Collection;

        // Weekly payout processing scans only workers who have settled customer payments.
        User::query()
            ->with('role')
            ->whereHas('role', fn ($query) => $query->where('slug', 'worker'))
            ->whereHas('workerPayments', fn ($query) => $query->where('status', Payment::STATUS_PAID))
            ->chunkById(100, function ($workers) use ($periodStart, $periodEnd, $payouts): void {
                foreach ($workers as $worker) {
                    // Skip workers who are already fully paid out.
                    if ($this->pendingPayout($worker) <= 0) {
                        continue;
                    }

                    $payout = DB::transaction(function () use ($worker, $periodStart, $periodEnd): WorkerPayout {
                        // Lock the worker before recalculating to avoid duplicate weekly payouts.
                        $lockedWorker = User::query()
                            ->with('role')
                            ->whereKey($worker->id)
                            ->lockForUpdate()
                            ->firstOrFail();
                        $lockedPendingPayout = $this->pendingPayout($lockedWorker);

                        // A second guard catches races where another payout finished first.
                        if ($lockedPendingPayout <= 0) {
                            throw ValidationException::withMessages([
                                'worker_id' => ['This worker does not have any pending payout.'],
                            ]);
                        }

                        $payout = WorkerPayout::create([
                            'worker_id' => $lockedWorker->id,
                            'processed_by' => null,
                            'amount' => $lockedPendingPayout,
                            'status' => WorkerPayout::STATUS_PAID,
                            'period_start' => $periodStart->toDateString(),
                            'period_end' => $periodEnd->toDateString(),
                            'reference' => sprintf('WEEKLY-%s-W%s-%s', $periodEnd->format('Ymd'), $lockedWorker->id, Str::upper(Str::random(6))),
                            'notes' => 'Automatic weekly payout',
                            'paid_at' => now(),
                        ]);

                        $this->audit->record('worker_payout.weekly_paid', null, $payout, [
                            'worker_id' => $lockedWorker->id,
                            'amount' => $lockedPendingPayout,
                            'period_start' => $periodStart->toDateString(),
                            'period_end' => $periodEnd->toDateString(),
                        ]);

                        return $payout->refresh()->load(['worker.role', 'processor.role']);
                    });

                    $payouts->push($payout);
                }
            });

        return $payouts;
    }

    public function pendingPayout(User $worker): float
    {
        // Pending payout is the worker's settled earnings minus amounts already paid out.
        $paidEarnings = (float) $worker->workerPayments()
            ->where('status', Payment::STATUS_PAID)
            ->sum('worker_earning');

        $paidOut = (float) $worker->workerPayouts()
            ->where('status', WorkerPayout::STATUS_PAID)
            ->sum('amount');

        return max(round($paidEarnings - $paidOut, 2), 0);
    }

    /**
     * @return Collection<int, WorkerPayout>
     */
    public function workerPayouts(User $worker): Collection
    {
        // Dashboard payout history shows the most recent processed transfers for the worker.
        return $worker->workerPayouts()
            ->with(['processor.role'])
            ->latest()
            ->limit(10)
            ->get();
    }
}
