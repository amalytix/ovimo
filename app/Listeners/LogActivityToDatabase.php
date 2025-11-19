<?php

namespace App\Listeners;

use App\Events\ContentPieceGenerated;
use App\Events\ContentPieceGenerationFailed;
use App\Events\OpenAIRequestFailed;
use App\Events\PasswordChanged;
use App\Events\PasswordReset;
use App\Events\PostFound;
use App\Events\SourceCreated;
use App\Events\SourceDeleted;
use App\Events\SourceMonitoringFailed;
use App\Events\SourceUpdated;
use App\Events\TokenLimitExceeded;
use App\Events\TwoFactorDisabled;
use App\Events\TwoFactorEnabled;
use App\Events\UserLoggedIn;
use App\Events\WebhookDeliveryFailed;
use App\Models\ActivityLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogActivityToDatabase implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(object $event): void
    {
        $logData = match (true) {
            $event instanceof UserLoggedIn => [
                'team_id' => $event->team->id,
                'user_id' => $event->user->id,
                'event_type' => 'user.login',
                'level' => 'info',
                'description' => 'User logged in',
                'ip_address' => $event->ipAddress,
                'user_agent' => $event->userAgent,
            ],

            $event instanceof TwoFactorEnabled => [
                'team_id' => $event->team->id,
                'user_id' => $event->user->id,
                'event_type' => 'user.2fa_enabled',
                'level' => 'info',
                'description' => 'Two-factor authentication enabled',
            ],

            $event instanceof TwoFactorDisabled => [
                'team_id' => $event->team->id,
                'user_id' => $event->user->id,
                'event_type' => 'user.2fa_disabled',
                'level' => 'info',
                'description' => 'Two-factor authentication disabled',
            ],

            $event instanceof PasswordChanged => [
                'team_id' => $event->team->id,
                'user_id' => $event->user->id,
                'event_type' => 'user.password_changed',
                'level' => 'info',
                'description' => 'Password changed',
            ],

            $event instanceof PasswordReset => [
                'team_id' => $event->team->id,
                'user_id' => $event->user->id,
                'event_type' => 'user.password_reset',
                'level' => 'info',
                'description' => 'Password reset via email',
            ],

            $event instanceof PostFound => [
                'team_id' => $event->source->team_id,
                'user_id' => null,
                'event_type' => 'post.found',
                'level' => 'info',
                'description' => "New post found: '{$event->post->external_title}'",
                'source_id' => $event->source->id,
                'post_id' => $event->post->id,
                'metadata' => [
                    'post_title' => $event->post->external_title,
                    'post_url' => $event->post->uri,
                    'source_name' => $event->source->internal_name,
                ],
            ],

            $event instanceof SourceCreated => [
                'team_id' => $event->source->team_id,
                'user_id' => $event->user->id,
                'event_type' => 'source.created',
                'level' => 'info',
                'description' => "Source created: '{$event->source->internal_name}'",
                'source_id' => $event->source->id,
                'metadata' => [
                    'source_name' => $event->source->internal_name,
                    'source_type' => $event->source->type,
                ],
            ],

            $event instanceof SourceUpdated => [
                'team_id' => $event->source->team_id,
                'user_id' => $event->user->id,
                'event_type' => 'source.updated',
                'level' => 'info',
                'description' => "Source updated: '{$event->source->internal_name}'",
                'source_id' => $event->source->id,
                'metadata' => [
                    'source_name' => $event->source->internal_name,
                ],
            ],

            $event instanceof SourceDeleted => [
                'team_id' => $event->teamId,
                'user_id' => $event->user->id,
                'event_type' => 'source.deleted',
                'level' => 'info',
                'description' => "Source deleted: '{$event->sourceName}'",
                'source_id' => $event->sourceId,
                'metadata' => [
                    'source_name' => $event->sourceName,
                ],
            ],

            $event instanceof SourceMonitoringFailed => [
                'team_id' => $event->source->team_id,
                'user_id' => null,
                'event_type' => 'source.monitoring_failed',
                'level' => 'error',
                'description' => "Failed to monitor source '{$event->source->internal_name}': {$event->errorMessage}",
                'source_id' => $event->source->id,
                'metadata' => [
                    'exception' => $event->errorMessage,
                    'source_url' => $event->source->url,
                ],
            ],

            $event instanceof ContentPieceGenerated => [
                'team_id' => $event->contentPiece->team_id,
                'user_id' => null,
                'event_type' => 'content_piece.generated',
                'level' => 'info',
                'description' => "Content piece '{$event->contentPiece->internal_name}' generated successfully",
                'metadata' => [
                    'content_piece_id' => $event->contentPiece->id,
                    'prompt_id' => $event->contentPiece->prompt_id,
                    'channel' => $event->contentPiece->channel,
                    'language' => $event->contentPiece->target_language,
                ],
            ],

            $event instanceof ContentPieceGenerationFailed => [
                'team_id' => $event->contentPiece->team_id,
                'user_id' => null,
                'event_type' => 'content_piece.generation_failed',
                'level' => 'error',
                'description' => "Content piece '{$event->contentPiece->internal_name}' generation failed",
                'metadata' => [
                    'content_piece_id' => $event->contentPiece->id,
                    'error_message' => $event->exception->getMessage(),
                    'error_type' => get_class($event->exception),
                ],
            ],

            $event instanceof OpenAIRequestFailed => [
                'team_id' => $event->team->id,
                'user_id' => $event->user?->id,
                'event_type' => 'openai.request_failed',
                'level' => 'error',
                'description' => "OpenAI API request failed for operation '{$event->operation}': {$event->errorMessage}",
                'metadata' => array_merge(
                    [
                        'operation' => $event->operation,
                        'exception' => $event->errorMessage,
                    ],
                    $event->metadata
                ),
            ],

            $event instanceof WebhookDeliveryFailed => [
                'team_id' => $event->webhook->team_id,
                'user_id' => null,
                'event_type' => 'webhook.delivery_failed',
                'level' => 'warning',
                'description' => "Failed to deliver webhook '{$event->webhook->name}': {$event->errorMessage}",
                'metadata' => array_merge(
                    [
                        'webhook_url' => $event->webhook->url,
                        'event' => $event->webhook->event,
                        'exception' => $event->errorMessage,
                    ],
                    $event->metadata
                ),
            ],

            $event instanceof TokenLimitExceeded => [
                'team_id' => $event->team->id,
                'user_id' => $event->user?->id,
                'event_type' => 'token.limit_exceeded',
                'level' => 'warning',
                'description' => 'Monthly token limit exceeded for team',
                'metadata' => [
                    'limit' => $event->limit,
                    'current_usage' => $event->currentUsage,
                    'operation' => $event->operation,
                ],
            ],

            default => null,
        };

        if ($logData !== null) {
            ActivityLog::create($logData);
        }
    }
}
