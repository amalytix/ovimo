<?php

namespace App\Console\Commands;

use App\Jobs\MonitorSource;
use App\Models\Source;
use Illuminate\Console\Command;
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

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Log::info('ScheduleSourceMonitoring command started', [
            'current_time' => now()->toDateTimeString(),
        ]);

        $sources = Source::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('next_check_at')
                    ->orWhere('next_check_at', '<=', now());
            })
            ->get();

        $count = $sources->count();
        $sourceIds = $sources->pluck('id')->toArray();

        Log::info('Sources query completed', [
            'count' => $count,
            'source_ids' => $sourceIds,
            'source_names' => $sources->pluck('internal_name', 'id')->toArray(),
        ]);

        if ($count === 0) {
            $this->info('No sources need monitoring at this time.');
            Log::info('No sources need monitoring - command exiting');

            return self::SUCCESS;
        }

        $this->info("Dispatching monitoring jobs for {$count} sources...");
        Log::info("Dispatching monitoring jobs for {$count} sources");

        foreach ($sources as $source) {
            MonitorSource::dispatch($source);
            $this->line("  - Dispatched job for source: {$source->internal_name}");
            Log::info('MonitorSource job dispatched', [
                'source_id' => $source->id,
                'source_name' => $source->internal_name,
                'next_check_at' => $source->next_check_at?->toDateTimeString(),
            ]);
        }

        $this->info("Done. {$count} jobs dispatched.");
        Log::info('ScheduleSourceMonitoring command completed', [
            'total_dispatched' => $count,
        ]);

        return self::SUCCESS;
    }
}
