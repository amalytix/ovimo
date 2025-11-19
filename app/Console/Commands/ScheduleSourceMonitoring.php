<?php

namespace App\Console\Commands;

use App\Jobs\MonitorSource;
use App\Models\Source;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScheduleSourceMonitoring extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sources:monitor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch monitoring jobs for sources that need to be checked';

    private int $reservationMinutes = 2;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Log::info('ScheduleSourceMonitoring command started', [
        //     'current_time' => now()->toDateTimeString(),
        // ]);

        $now = now();
        $reservationUntil = $now->copy()->addMinutes($this->reservationMinutes);

        $processed = 0;
        $this->info('Scanning for sources to monitor...');

        Source::query()
            ->where('is_active', true)
            ->where(function ($query) use ($now) {
                $query->whereNull('next_check_at')
                    ->orWhere('next_check_at', '<=', $now);
            })
            ->orderBy('next_check_at')
            ->chunkById(200, function ($chunk) use ($reservationUntil, &$processed) {
                // Claim a limited batch inside a transaction to avoid double-dispatching
                $claimed = DB::transaction(function () use ($chunk, $reservationUntil) {
                    $claimedSources = collect();

                    foreach ($chunk as $source) {
                        // Re-check inside lock in case another worker updated next_check_at
                        $fresh = Source::lockForUpdate()->find($source->id);

                        if (! $fresh || ! $fresh->is_active) {
                            continue;
                        }

                        if ($fresh->next_check_at && $fresh->next_check_at->isFuture()) {
                            continue;
                        }

                        $fresh->updateQuietly(['next_check_at' => $reservationUntil]);
                        $claimedSources->push($fresh);
                    }

                    return $claimedSources;
                });

                foreach ($claimed as $source) {
                    MonitorSource::dispatch($source);
                    $processed++;
                    $this->line("  - Dispatched job for source: {$source->internal_name}");
                }
            });

        if ($processed === 0) {
            $this->info('No sources need monitoring at this time.');
            // Log::info('No sources need monitoring - command exiting');

            return self::SUCCESS;
        }

        $this->info("Done. {$processed} jobs dispatched.");
        // Log::info('ScheduleSourceMonitoring command completed', [
        //     'total_dispatched' => $count,
        // ]);

        return self::SUCCESS;
    }
}
