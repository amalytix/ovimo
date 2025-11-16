<?php

namespace App\Jobs;

use App\Models\Webhook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWebhookNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 300;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public Webhook $webhook,
        public array $payload
    ) {}

    public function handle(): void
    {
        if (! $this->webhook->is_active) {
            return;
        }

        try {
            $headers = [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Ovimo-Webhook/1.0',
            ];

            // Add signature if secret is configured
            if ($this->webhook->secret) {
                $signature = hash_hmac('sha256', json_encode($this->payload), $this->webhook->secret);
                $headers['X-Webhook-Signature'] = $signature;
            }

            $response = Http::timeout(30)
                ->withHeaders($headers)
                ->post($this->webhook->url, $this->payload);

            if ($response->successful()) {
                $this->webhook->update([
                    'last_triggered_at' => now(),
                    'failure_count' => 0,
                ]);

                Log::info("Webhook {$this->webhook->id} delivered successfully");
            } else {
                $this->handleFailure("HTTP {$response->status()}");
            }
        } catch (\Exception $e) {
            $this->handleFailure($e->getMessage());
            throw $e;
        }
    }

    private function handleFailure(string $reason): void
    {
        $this->webhook->increment('failure_count');

        // Disable webhook after 10 consecutive failures
        if ($this->webhook->failure_count >= 10) {
            $this->webhook->update(['is_active' => false]);
            Log::warning("Webhook {$this->webhook->id} disabled after 10 failures");
        }

        Log::error("Webhook {$this->webhook->id} failed: {$reason}");
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function dispatchForEvent(string $event, int $teamId, array $data): void
    {
        $webhooks = Webhook::where('team_id', $teamId)
            ->where('event', $event)
            ->where('is_active', true)
            ->get();

        foreach ($webhooks as $webhook) {
            self::dispatch($webhook, [
                'event' => $event,
                'timestamp' => now()->toIso8601String(),
                'data' => $data,
            ]);
        }
    }
}
