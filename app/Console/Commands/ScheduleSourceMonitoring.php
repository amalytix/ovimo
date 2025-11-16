<?php

namespace App\Console\Commands;

use App\Jobs\MonitorSource;
use App\Models\Source;
use Illuminate\Console\Command;

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
        $sources = Source::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('next_check_at')
                    ->orWhere('next_check_at', '<=', now());
            })
            ->get();

        $count = $sources->count();

        if ($count === 0) {
            $this->info('No sources need monitoring at this time.');

            return self::SUCCESS;
        }

        $this->info("Dispatching monitoring jobs for {$count} sources...");

        foreach ($sources as $source) {
            MonitorSource::dispatch($source);
            $this->line("  - Dispatched job for source: {$source->internal_name}");
        }

        $this->info("Done. {$count} jobs dispatched.");

        return self::SUCCESS;
    }
}
