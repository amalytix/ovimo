<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    /** @use HasFactory<\Database\Factories\ActivityLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const EVENT_TYPES = [
        // User events
        'user.login' => 'User Login',
        'user.2fa_enabled' => '2FA Enabled',
        'user.2fa_disabled' => '2FA Disabled',
        'user.password_changed' => 'Password Changed',
        'user.password_reset' => 'Password Reset',

        // Domain events
        'post.found' => 'Post Found',
        'source.created' => 'Source Created',
        'source.updated' => 'Source Updated',
        'source.deleted' => 'Source Deleted',
        'content_piece.generated' => 'Content Piece Generated',
        'integration.linkedin_connected' => 'LinkedIn Connected',
        'integration.linkedin_disconnected' => 'LinkedIn Disconnected',
        'content.published_to_linkedin' => 'Content Published to LinkedIn',

        // Team events
        'team.invitation_sent' => 'Team Invitation Sent',
        'team.invitation_accepted' => 'Team Invitation Accepted',
        'team.invitation_revoked' => 'Team Invitation Revoked',
        'team.member_removed' => 'Team Member Removed',
        'team.member_left' => 'Team Member Left',

        // Error/Warning events
        'source.monitoring_failed' => 'Source Monitoring Failed',
        'content_piece.generation_failed' => 'Content Generation Failed',
        'content.linkedin_publish_failed' => 'LinkedIn Publish Failed',
        'openai.request_failed' => 'OpenAI Request Failed',
        'webhook.delivery_failed' => 'Webhook Delivery Failed',
        'token.limit_exceeded' => 'Token Limit Exceeded',
    ];

    protected $fillable = [
        'team_id',
        'user_id',
        'event_type',
        'level',
        'description',
        'source_id',
        'post_id',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
