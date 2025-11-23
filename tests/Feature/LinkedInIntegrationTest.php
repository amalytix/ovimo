<?php

use App\Events\LinkedInIntegrationConnected;
use App\Events\LinkedInIntegrationDisconnected;
use App\Models\SocialIntegration;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('services.linkedin', [
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'redirect_uri' => 'https://example.com/integrations/linkedin/callback-member',
        'scopes' => ['openid', 'profile', 'w_member_social', 'r_basicprofile'],
    ]);
});

test('connect endpoint stores oauth state and redirects to LinkedIn', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->get('/integrations/linkedin/connect');

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->toContain('https://www.linkedin.com/oauth/v2/authorization');

    $session = session()->get('linkedin.oauth');

    expect($session['state'])->not->toBeEmpty()
        ->and($session['code_verifier'])->not->toBeEmpty()
        ->and($session['team_id'])->toBe($team->id);
});

test('callback exchanges code and creates social integration', function () {
    [$user, $team] = createUserWithTeam();
    Event::fake([LinkedInIntegrationConnected::class]);

    $this->actingAs($user)->get('/integrations/linkedin/connect');
    $session = session()->get('linkedin.oauth');

    Http::fake([
        'https://www.linkedin.com/oauth/v2/accessToken' => Http::response([
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 3600,
            'scope' => 'openid profile w_member_social r_basicprofile',
        ]),
        'https://api.linkedin.com/v2/userinfo' => Http::response([
            'sub' => 'abc123',
            'name' => 'Jane Doe',
            'picture' => 'https://example.com/avatar.jpg',
        ]),
        'https://api.linkedin.com/v2/me*' => Http::response([
            'id' => 'abc123',
            'vanityName' => 'janedoe',
        ]),
    ]);

    $response = $this->actingAs($user)->get('/integrations/linkedin/callback?code=test-code&state='.$session['state']);

    $response->assertRedirect(route('team-settings.index', ['tab' => 'integrations']));

    $integration = SocialIntegration::first();

    expect($integration)->not->toBeNull()
        ->and($integration->team_id)->toBe($team->id)
        ->and($integration->platform_user_id)->toBe('abc123')
        ->and($integration->platform_username)->toBe('janedoe')
        ->and($integration->token_expires_at)->not->toBeNull()
        ->and($integration->is_active)->toBeTrue();

    Event::assertDispatched(LinkedInIntegrationConnected::class);
});

test('callback rejects invalid state and does not create integration', function () {
    [$user, $team] = createUserWithTeam();

    session()->put('linkedin.oauth', [
        'state' => 'expected-state',
        'code_verifier' => 'verifier',
        'team_id' => $team->id,
    ]);

    $response = $this->actingAs($user)->get('/integrations/linkedin/callback?code=test-code&state=wrong-state');

    $response->assertRedirect(route('team-settings.index', ['tab' => 'integrations']));
    $response->assertSessionHas('error');

    expect(SocialIntegration::count())->toBe(0);
});

test('users can disconnect a LinkedIn integration', function () {
    [$user, $team] = createUserWithTeam();
    Event::fake([LinkedInIntegrationDisconnected::class]);

    $integration = SocialIntegration::factory()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->delete("/integrations/linkedin/{$integration->id}");

    $response->assertRedirect(route('team-settings.index', ['tab' => 'integrations']));
    expect($integration->fresh()->is_active)->toBeFalse();

    Event::assertDispatched(LinkedInIntegrationDisconnected::class);
});
