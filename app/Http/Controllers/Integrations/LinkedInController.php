<?php

namespace App\Http\Controllers\Integrations;

use App\Events\LinkedInIntegrationConnected;
use App\Events\LinkedInIntegrationDisconnected;
use App\Http\Controllers\Controller;
use App\Models\SocialIntegration;
use App\Services\LinkedIn\LinkedInOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LinkedInController extends Controller
{
    private const SESSION_KEY = 'linkedin.oauth';

    public function __construct(private readonly LinkedInOAuthService $linkedInOAuthService) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', SocialIntegration::class);

        $teamId = auth()->user()->current_team_id;

        $integrations = SocialIntegration::query()
            ->where('team_id', $teamId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SocialIntegration $integration) => [
                'id' => $integration->id,
                'platform' => $integration->platform,
                'platform_user_id' => $integration->platform_user_id,
                'platform_username' => $integration->platform_username,
                'profile_data' => $integration->profile_data,
                'scopes' => $integration->scopes,
                'is_active' => $integration->is_active,
                'connected_at' => $integration->created_at?->toIso8601String(),
                'token_expires_at' => $integration->token_expires_at?->toIso8601String(),
            ]);

        return response()->json([
            'integrations' => $integrations,
        ]);
    }

    public function redirect(): RedirectResponse
    {
        $this->authorize('create', SocialIntegration::class);

        $state = $this->linkedInOAuthService->generateState();
        $codeVerifier = $this->linkedInOAuthService->generateCodeVerifier();

        session()->put(self::SESSION_KEY, [
            'state' => $state,
            'code_verifier' => $codeVerifier,
            'team_id' => auth()->user()->current_team_id,
        ]);

        $authorizationUrl = $this->linkedInOAuthService->generateAuthUrl($state, $codeVerifier);

        return redirect()->away($authorizationUrl);
    }

    public function callback(Request $request): RedirectResponse
    {
        $sessionData = session()->pull(self::SESSION_KEY, []);

        $debugId = (string) Str::uuid();

        Log::info('LinkedIn callback received', [
            'debug_id' => $debugId,
            'state' => $request->input('state'),
            'code_present' => $request->filled('code'),
            'code_length' => $request->filled('code') ? strlen($request->string('code')->toString()) : 0,
            'session_state' => $sessionData['state'] ?? null,
            'session_team' => $sessionData['team_id'] ?? null,
            'session_has_verifier' => isset($sessionData['code_verifier']),
            'session_verifier_length' => isset($sessionData['code_verifier']) ? strlen($sessionData['code_verifier']) : 0,
            'request_url' => $request->fullUrl(),
            'all_query_params' => $request->query(),
        ]);

        try {
            $this->authorize('create', SocialIntegration::class);

            if ($request->input('error')) {
                return $this->redirectWithError($request->input('error_description') ?? 'LinkedIn authorization was cancelled.');
            }

            if (($request->input('state') ?? null) !== ($sessionData['state'] ?? null)) {
                Log::warning('LinkedIn OAuth state mismatch', [
                    'debug_id' => $debugId,
                    'expected' => $sessionData['state'] ?? null,
                    'received' => $request->input('state'),
                ]);

                return $this->redirectWithError('Invalid or expired LinkedIn authorization. Please start the connection again.');
            }

            if (($sessionData['team_id'] ?? null) !== auth()->user()->current_team_id) {
                return $this->redirectWithError('LinkedIn connection was started for a different team. Please retry.');
            }

            if (! $request->filled('code') || empty($sessionData['code_verifier'])) {
                Log::warning('LinkedIn callback missing code or verifier', [
                    'debug_id' => $debugId,
                    'code_present' => $request->filled('code'),
                    'verifier_present' => ! empty($sessionData['code_verifier']),
                ]);

                return $this->redirectWithError('Authorization code missing from LinkedIn. Please try again.');
            }

            $tokens = $this->linkedInOAuthService->exchangeCodeForToken(
                $request->string('code')->toString(),
                $sessionData['code_verifier']
            );

            $scopes = $tokens['scopes'] ?? [];
            $requiredScopes = config('services.linkedin.scopes', []);

            if (! $this->linkedInOAuthService->validateScopes($scopes, $requiredScopes)) {
                return $this->redirectWithError('LinkedIn did not grant all required permissions. Please approve all requested scopes.');
            }

            $profile = $this->linkedInOAuthService->getUserProfile($tokens['access_token']);

            if (empty($profile['id'])) {
                Log::warning('LinkedIn profile missing id', ['debug_id' => $debugId, 'profile' => $profile]);

                return $this->redirectWithError('Unable to fetch LinkedIn profile. Please try again.');
            }

            $integration = SocialIntegration::updateOrCreate(
                [
                    'team_id' => auth()->user()->current_team_id,
                    'platform' => SocialIntegration::PLATFORM_LINKEDIN,
                    'platform_user_id' => $profile['id'],
                ],
                [
                    'user_id' => auth()->id(),
                    'platform_username' => $profile['username'] ?? $profile['name'],
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'],
                    'token_expires_at' => $tokens['expires_at'] ?? null,
                    'scopes' => $scopes,
                    'profile_data' => $profile['raw'] ?? $profile,
                    'is_active' => true,
                ]
            );

            event(new LinkedInIntegrationConnected($integration, $request->user()));

            return redirect()
                ->route('team-settings.index', ['tab' => 'integrations'])
                ->with('success', "LinkedIn profile '{$integration->platform_username}' connected.");
        } catch (\Throwable $exception) {
            Log::error('LinkedIn OAuth callback failed', [
                'debug_id' => $debugId,
                'error' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return $this->redirectWithError('Unable to complete LinkedIn authorization. Please try again. (Ref: '.$debugId.')');
        }
    }

    public function disconnect(Request $request, SocialIntegration $integration): RedirectResponse
    {
        $this->authorize('delete', $integration);

        $integration->update(['is_active' => false]);

        event(new LinkedInIntegrationDisconnected($integration, $request->user()));

        return redirect()
            ->route('team-settings.index', ['tab' => 'integrations'])
            ->with('success', 'LinkedIn integration disconnected.');
    }

    private function redirectWithError(string $message): RedirectResponse
    {
        return redirect()
            ->route('team-settings.index', ['tab' => 'integrations'])
            ->with('error', $message);
    }
}
