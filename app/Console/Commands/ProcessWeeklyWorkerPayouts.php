<?php

namespace App\Console\Commands;

use App\Services\Payment\WorkerPayoutService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('payouts:process-weekly {--date= : Week ending date in Y-m-d format}')]
#[Description('Create paid payout records for all workers with pending paid earnings.')]
class ProcessWeeklyWorkerPayouts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(WorkerPayoutService $payouts): int
    {
        $today = CarbonImmutable::now();
        // The payout period ends on the requested date or the most recent completed Sunday.
        $periodEnd = $this->option('date')
            ? CarbonImmutable::createFromFormat('Y-m-d', (string) $this->option('date'))->endOfDay()
            : ($today->isSunday() ? $today->endOfDay() : $today->previous(CarbonImmutable::SUNDAY)->endOfDay());
        $periodStart = $periodEnd->subDays(6)->startOfDay();

        $createdPayouts = $payouts->processWeeklyPayouts($periodStart, $periodEnd);

        $this->info(sprintf('Processed %d worker payout(s).', $createdPayouts->count()));

        $createdPayouts->each(function ($payout): void {
            $this->line(sprintf(
                'Worker #%d paid Rs %s (%s to %s)',
                $payout->worker_id,
                $payout->amount,
                $payout->period_start?->toDateString(),
                $payout->period_end?->toDateString(),
            ));
        });

        return self::SUCCESS;
    }
}
