<?php

namespace App\Services\LinkedIn;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LinkedInOAuthService
{
    private const AUTHORIZE_URL = 'https://www.linkedin.com/oauth/v2/authorization';

    private const TOKEN_URL = 'https://www.linkedin.com/oauth/v2/accessToken';

    private const USERINFO_URL = 'https://api.linkedin.com/v2/userinfo';

    private const ME_URL = 'https://api.linkedin.com/v2/me?projection=(id,localizedFirstName,localizedLastName,vanityName,profilePicture(displayImage~:playableStreams))';

    private const REST_VERSION = '202405';

    public function __construct(private readonly ConfigRepository $config) {}

    public function generateState(): string
    {
        return Str::random(40);
    }

    public function generateCodeVerifier(): string
    {
        return Str::random(96);
    }

    public function generateAuthUrl(string $state, string $codeVerifier): string
    {
        $codeChallenge = $this->codeChallenge($codeVerifier);

        $params = [
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'scope' => implode(' ', $this->scopes()),
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];

        Log::info('LinkedIn OAuth: Generating authorization URL', [
            'redirect_uri' => $params['redirect_uri'],
            'client_id_tail' => substr($params['client_id'], -4),
            'scopes' => $params['scope'],
            'code_verifier_length' => strlen($codeVerifier),
            'code_challenge_length' => strlen($codeChallenge),
            'state_length' => strlen($state),
        ]);

        return self::AUTHORIZE_URL.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCodeForToken(string $code, string $codeVerifier): array
    {
        $payload = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
            'code_verifier' => $codeVerifier,
        ];

        Log::info('LinkedIn OAuth: Exchanging code for token', [
            'code_length' => strlen($code),
            'code_prefix' => substr($code, 0, 10).'...',
            'redirect_uri' => $payload['redirect_uri'],
            'client_id' => $this->clientId(),
            'auth_method' => 'basic_auth',
            'code_verifier_length' => strlen($codeVerifier),
            'grant_type' => $payload['grant_type'],
        ]);

        $response = $this->postToken($payload);

        return $this->normalizeTokenResponse($response);
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        $payload = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ];

        $response = $this->postToken($payload);

        return $this->normalizeTokenResponse($response);
    }

    public function getUserProfile(string $accessToken): array
    {
        $userInfo = $this->authorizedRequest($accessToken)
            ->get(self::USERINFO_URL)
            ->throw()
            ->json();

        $me = $this->authorizedRequest($accessToken)
            ->get(self::ME_URL)
            ->throw()
            ->json();

        return [
            'id' => $userInfo['sub'] ?? $me['id'] ?? null,
            'name' => $userInfo['name'] ?? trim(($me['localizedFirstName'] ?? '').' '.($me['localizedLastName'] ?? '')),
            'username' => $me['vanityName'] ?? null,
            'picture' => $userInfo['picture'] ?? $this->extractProfilePicture($me),
            'raw' => [
                'userinfo' => $userInfo,
                'me' => $me,
            ],
        ];
    }

    public function validateScopes(array|string $returnedScopes, array $requiredScopes): bool
    {
        $scopes = $this->parseScopes($returnedScopes);

        return empty(array_diff($requiredScopes, $scopes));
    }

    private function clientId(): string
    {
        return (string) $this->config->get('services.linkedin.client_id');
    }

    private function clientSecret(): string
    {
        return (string) $this->config->get('services.linkedin.client_secret');
    }

    private function redirectUri(): string
    {
        return (string) $this->config->get('services.linkedin.redirect_uri');
    }

    private function scopes(): array
    {
        return $this->config->get('services.linkedin.scopes', []);
    }

    private function codeChallenge(string $codeVerifier): string
    {
        $hashed = hash('sha256', $codeVerifier, true);

        return rtrim(strtr(base64_encode($hashed), '+/', '-_'), '=');
    }

    private function parseScopes(array|string|null $scopes): array
    {
        if ($scopes === null) {
            return [];
        }

        if (is_string($scopes)) {
            return array_values(array_filter(explode(' ', $scopes)));
        }

        return array_values(array_filter($scopes));
    }

    private function normalizeTokenResponse(array $response): array
    {
        $scopes = $this->parseScopes($response['scope'] ?? null);

        return [
            'access_token' => $response['access_token'] ?? null,
            'refresh_token' => $response['refresh_token'] ?? null,
            'expires_in' => $response['expires_in'] ?? null,
            'expires_at' => isset($response['expires_in'])
                ? now()->addSeconds((int) $response['expires_in'])
                : null,
            'scopes' => $scopes,
        ];
    }

    private function extractProfilePicture(array $me): ?string
    {
        $elements = Arr::get($me, 'profilePicture.displayImage~.elements', []);
        $identifiers = collect($elements)
            ->sortByDesc(fn (array $element) => Arr::get($element, 'data.size', 0))
            ->pluck('identifiers')
            ->flatten(1);

        return $identifiers->first()['identifier'] ?? null;
    }

    private function authorizedRequest(string $accessToken): PendingRequest
    {
        return $this->http()
            ->withToken($accessToken)
            ->withHeaders([
                'LinkedIn-Version' => self::REST_VERSION,
                'X-RestLi-Protocol-Version' => '2.0.0',
            ]);
    }

    private function http(): PendingRequest
    {
        return Http::retry(2, 200);
    }

    private function postToken(array $payload): array
    {
        Log::info('LinkedIn OAuth: About to POST to token endpoint', [
            'url' => self::TOKEN_URL,
            'grant_type' => $payload['grant_type'] ?? null,
            'redirect_uri' => $payload['redirect_uri'] ?? null,
            'client_id' => $this->clientId(),
            'auth_method' => 'basic_auth',
            'has_code' => isset($payload['code']),
            'has_code_verifier' => isset($payload['code_verifier']),
            'has_refresh_token' => isset($payload['refresh_token']),
        ]);

        // Use retry but don't throw on failure - we want to inspect the error response
        $response = Http::retry(2, 200, throw: false)
            ->withBasicAuth($this->clientId(), $this->clientSecret())
            ->asForm()
            ->post(self::TOKEN_URL, $payload);

        Log::info('LinkedIn OAuth: Received response from token endpoint', [
            'status' => $response->status(),
            'successful' => $response->successful(),
            'failed' => $response->failed(),
        ]);

        if ($response->failed()) {
            $errorBody = null;
            try {
                $errorBody = $response->json();
            } catch (\Throwable $e) {
                $errorBody = $response->body();
            }

            Log::error('LinkedIn token request failed', [
                'status' => $response->status(),
                'body' => $errorBody,
                'headers' => $response->headers(),
                'redirect_uri' => $payload['redirect_uri'] ?? null,
                'client_id' => $this->clientId(),
                'grant_type' => $payload['grant_type'] ?? null,
                'code_length' => isset($payload['code']) ? strlen($payload['code']) : null,
                'code_verifier_length' => isset($payload['code_verifier']) ? strlen($payload['code_verifier']) : null,
                'auth_method' => 'basic_auth',
            ]);

            $response->throw();
        }

        Log::info('LinkedIn OAuth: Token request successful', [
            'has_access_token' => isset($response->json()['access_token']),
            'has_refresh_token' => isset($response->json()['refresh_token']),
            'expires_in' => $response->json()['expires_in'] ?? null,
        ]);

        return $response->json();
    }
}
