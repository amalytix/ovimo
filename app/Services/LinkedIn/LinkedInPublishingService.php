<?php

namespace App\Services\LinkedIn;

use App\Events\ContentPublishedToLinkedIn;
use App\Events\LinkedInPublishingFailed;
use App\Models\ContentPiece;
use App\Models\Media;
use App\Models\SocialIntegration;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class LinkedInPublishingService
{
    private const POSTS_ENDPOINT = 'https://api.linkedin.com/rest/posts';

    private const REST_VERSION = '202405';

    public function __construct(private readonly LinkedInOAuthService $oauth) {}

    public function publishPost(SocialIntegration $integration, ContentPiece $contentPiece): array
    {
        $integration = $this->refreshTokenIfNeeded($integration);

        $authorUrn = $this->authorUrn($integration->platform_user_id);
        $message = $this->buildMessage($contentPiece);
        $mediaUrns = $this->collectMediaUrns($integration, $contentPiece);
        $payload = $this->buildPostPayload($authorUrn, $message, $mediaUrns);

        try {
            $response = $this->request($integration->access_token)
                ->post(self::POSTS_ENDPOINT, $payload)
                ->throw();

            $urn = $response->header('x-restli-id');

            event(new ContentPublishedToLinkedIn($integration, $contentPiece, [
                'urn' => $urn,
                'payload' => $payload,
            ]));

            return [
                'urn' => $urn,
                'payload' => $payload,
            ];
        } catch (Throwable $exception) {
            event(new LinkedInPublishingFailed($integration, $contentPiece, $exception));

            throw $exception;
        }
    }

    public function refreshTokenIfNeeded(SocialIntegration $integration): SocialIntegration
    {
        if ($integration->token_expires_at === null || $integration->token_expires_at->isFuture()) {
            return $integration;
        }

        if (empty($integration->refresh_token)) {
            return $integration;
        }

        $tokens = $this->oauth->refreshAccessToken($integration->refresh_token);

        $integration->fill([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? $integration->refresh_token,
            'token_expires_at' => $tokens['expires_at'],
            'scopes' => $tokens['scopes'],
        ])->save();

        return $integration;
    }

    public function buildPostPayload(string $authorUrn, string $message, array $mediaUrns = []): array
    {
        $content = [];

        if (count($mediaUrns) === 1) {
            $content = [
                'media' => [
                    'id' => $mediaUrns[0],
                ],
            ];
        } elseif (count($mediaUrns) > 1) {
            $content = [
                'multiImage' => [
                    'images' => collect($mediaUrns)->map(fn (string $urn) => ['id' => $urn])->values()->all(),
                ],
            ];
        }

        return [
            'author' => $authorUrn,
            'commentary' => $message,
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'content' => $content,
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ];
    }

    private function collectMediaUrns(SocialIntegration $integration, ContentPiece $contentPiece): array
    {
        // Media upload support will be implemented with LinkedIn upload APIs; currently no-op.
        return $contentPiece->media->map(fn (Media $media) => $media->metadata['linkedin_urn'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    private function buildMessage(ContentPiece $contentPiece): string
    {
        $text = $contentPiece->edited_text
            ?? $contentPiece->research_text
            ?? $contentPiece->briefing_text
            ?? $contentPiece->internal_name;

        return Str::limit(trim((string) $text), 3000, '...');
    }

    private function authorUrn(string $platformUserId): string
    {
        return str_starts_with($platformUserId, 'urn:li:')
            ? $platformUserId
            : 'urn:li:person:'.$platformUserId;
    }

    private function request(string $accessToken)
    {
        return Http::withToken($accessToken)
            ->withHeaders([
                'LinkedIn-Version' => self::REST_VERSION,
                'X-Restli-Protocol-Version' => '2.0.0',
            ])
            ->retry(2, 200, throw: false)
            ->throwIf(fn (RequestException $exception) => $exception->response->status() >= 500);
    }
}
