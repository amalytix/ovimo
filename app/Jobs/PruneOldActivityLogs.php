<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PruneOldActivityLogs implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $cutoffDate = now()->subDays(30);

        $deletedCount = ActivityLog::where('created_at', '<', $cutoffDate)->delete();

        Log::info('Pruned activity logs older than 30 days', [
            'cutoff_date' => $cutoffDate->toDateTimeString(),
            'deleted_count' => $deletedCount,
        ]);
    }
}
