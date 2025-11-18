<?php

use App\Models\User;

test('guests cannot access team settings', function () {
    $this->get('/team-settings')->assertRedirect('/login');
});

test('authenticated users can view team settings', function () {
    [$user, $team] = createUserWithTeam();

    $this->actingAs($user)
        ->get('/team-settings')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Index')
            ->has('team')
            ->where('team.id', $team->id)
        );
});

test('team owner can update settings', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->put('/team-settings', [
        'name' => 'Updated Team Name',
        'post_auto_hide_days' => 30,
        'monthly_token_limit' => 5000000,
        'relevancy_prompt' => 'Custom AI prompt for relevancy scoring.',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'name' => 'Updated Team Name',
        'post_auto_hide_days' => 30,
        'monthly_token_limit' => 5000000,
    ]);
});

test('non-owner cannot update team settings', function () {
    [$owner, $team] = createUserWithTeam();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member->id, ['role' => 'member']);

    $this->actingAs($member)->put('/team-settings', [
        'name' => 'Hacked Team Name',
        'post_auto_hide_days' => null,
        'monthly_token_limit' => 10000000,
        'relevancy_prompt' => null,
    ])->assertForbidden();

    $this->assertDatabaseMissing('teams', [
        'id' => $team->id,
        'name' => 'Hacked Team Name',
    ]);
});

test('team settings validation requires name', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->put('/team-settings', [
        'name' => '',
    ]);

    $response->assertSessionHasErrors(['name']);
});

test('team settings accepts nullable fields', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->put('/team-settings', [
        'name' => 'Team Name',
        'post_auto_hide_days' => null,
        'monthly_token_limit' => 10000000,
        'relevancy_prompt' => null,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'name' => 'Team Name',
        'post_auto_hide_days' => null,
        'relevancy_prompt' => null,
    ]);
});

test('team settings validates post_auto_hide_days range', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->put('/team-settings', [
        'name' => 'Team Name',
        'post_auto_hide_days' => 400, // exceeds max 365
    ]);

    $response->assertSessionHasErrors(['post_auto_hide_days']);
});

test('team owner can update keyword filtering settings', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->put('/team-settings', [
        'name' => 'Team Name',
        'positive_keywords' => "climate\nrenewable\nsustainability",
        'negative_keywords' => "sponsored\nadvertisement",
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'positive_keywords' => "climate\nrenewable\nsustainability",
        'negative_keywords' => "sponsored\nadvertisement",
    ]);
});

test('team settings includes keyword fields in response', function () {
    [$user, $team] = createUserWithTeam();

    $team->update([
        'positive_keywords' => "climate\nrenewable",
        'negative_keywords' => 'sponsored',
    ]);

    $this->actingAs($user)
        ->get('/team-settings')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Index')
            ->where('team.positive_keywords', "climate\nrenewable")
            ->where('team.negative_keywords', 'sponsored')
        );
});

test('team settings accepts null keyword fields', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->put('/team-settings', [
        'name' => 'Team Name',
        'positive_keywords' => null,
        'negative_keywords' => null,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'positive_keywords' => null,
        'negative_keywords' => null,
    ]);
});

// Export Tests

test('team owner can export sources', function () {
    [$user, $team] = createUserWithTeam();
    $source = \App\Models\Source::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->post('/team-settings/export-sources');

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/json');
    $response->assertHeader('content-disposition');

    $data = $response->json();
    expect($data)->toHaveCount(1);
    expect($data[0]['internal_name'])->toBe($source->internal_name);
});

test('export includes only specified fields', function () {
    [$user, $team] = createUserWithTeam();
    \App\Models\Source::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->post('/team-settings/export-sources');

    $data = $response->json();
    $exportedSource = $data[0];

    expect($exportedSource)->toHaveKeys([
        'internal_name',
        'type',
        'url',
        'css_selector_title',
        'css_selector_link',
        'keywords',
        'monitoring_interval',
        'is_active',
        'should_notify',
        'auto_summarize',
        'bypass_keyword_filter',
        'tags',
    ]);

    expect($exportedSource)->not->toHaveKey('id');
    expect($exportedSource)->not->toHaveKey('team_id');
    expect($exportedSource)->not->toHaveKey('created_at');
});

test('export includes tags as array of names', function () {
    [$user, $team] = createUserWithTeam();
    $source = \App\Models\Source::factory()->create(['team_id' => $team->id]);
    $tag1 = \App\Models\Tag::factory()->create(['team_id' => $team->id, 'name' => 'Technology']);
    $tag2 = \App\Models\Tag::factory()->create(['team_id' => $team->id, 'name' => 'News']);
    $source->tags()->sync([$tag1->id, $tag2->id]);

    $response = $this->actingAs($user)->post('/team-settings/export-sources');

    $data = $response->json();
    expect($data[0]['tags'])->toEqual(['Technology', 'News']);
});

test('export with no sources returns empty array', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/team-settings/export-sources');

    $response->assertSuccessful();
    expect($response->json())->toBeArray()->toBeEmpty();
});

test('export limited to 1000 sources', function () {
    [$user, $team] = createUserWithTeam();
    \App\Models\Source::factory(1050)->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->post('/team-settings/export-sources');

    $data = $response->json();
    expect($data)->toHaveCount(1000);
});

test('non-owner cannot export sources', function () {
    [$owner, $team] = createUserWithTeam();
    $member = \App\Models\User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member->id, ['role' => 'member']);

    $this->actingAs($member)
        ->post('/team-settings/export-sources')
        ->assertForbidden();
});

test('export only includes current team sources', function () {
    [$user, $team] = createUserWithTeam();
    $source1 = \App\Models\Source::factory()->create(['team_id' => $team->id, 'internal_name' => 'Team1 Source']);

    [$otherUser, $otherTeam] = createUserWithTeam();
    \App\Models\Source::factory()->create(['team_id' => $otherTeam->id, 'internal_name' => 'Team2 Source']);

    $response = $this->actingAs($user)->post('/team-settings/export-sources');

    $data = $response->json();
    expect($data)->toHaveCount(1);
    expect($data[0]['internal_name'])->toBe('Team1 Source');
});

// Import Tests

test('team owner can import sources from valid JSON', function () {
    [$user, $team] = createUserWithTeam();

    $jsonData = [
        [
            'internal_name' => 'Test Source',
            'type' => 'RSS',
            'url' => 'https://example.com/feed.xml',
            'css_selector_title' => null,
            'css_selector_link' => null,
            'keywords' => 'tech, news',
            'monitoring_interval' => 'HOURLY',
            'is_active' => true,
            'should_notify' => false,
            'auto_summarize' => true,
            'bypass_keyword_filter' => false,
            'tags' => [],
        ],
    ];

    $file = \Illuminate\Http\Testing\File::create('sources.json', json_encode($jsonData));

    $response = $this->actingAs($user)->post('/team-settings/import-sources', [
        'file' => $file,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('sources', [
        'team_id' => $team->id,
        'internal_name' => 'Test Source',
        'url' => 'https://example.com/feed.xml',
    ]);
});

test('import skips duplicate sources with same URL', function () {
    [$user, $team] = createUserWithTeam();
    \App\Models\Source::factory()->create([
        'team_id' => $team->id,
        'url' => 'https://example.com/feed.xml',
    ]);

    $jsonData = [
        [
            'internal_name' => 'Duplicate Source',
            'type' => 'RSS',
            'url' => 'https://example.com/feed.xml',
            'css_selector_title' => null,
            'css_selector_link' => null,
            'keywords' => null,
            'monitoring_interval' => 'HOURLY',
            'is_active' => true,
            'should_notify' => false,
            'auto_summarize' => true,
            'bypass_keyword_filter' => false,
            'tags' => [],
        ],
    ];

    $file = \Illuminate\Http\Testing\File::create('sources.json', json_encode($jsonData));

    $response = $this->actingAs($user)->post('/team-settings/import-sources', [
        'file' => $file,
    ]);

    $response->assertRedirect();
    expect($response->getSession()->get('success'))->toContain('0 source(s) imported');
    expect($response->getSession()->get('success'))->toContain('1 duplicate(s) skipped');

    $this->assertDatabaseCount('sources', 1);
});

test('import creates tags if they do not exist', function () {
    [$user, $team] = createUserWithTeam();

    $jsonData = [
        [
            'internal_name' => 'Test Source',
            'type' => 'RSS',
            'url' => 'https://example.com/feed.xml',
            'css_selector_title' => null,
            'css_selector_link' => null,
            'keywords' => null,
            'monitoring_interval' => 'HOURLY',
            'is_active' => true,
            'should_notify' => false,
            'auto_summarize' => true,
            'bypass_keyword_filter' => false,
            'tags' => ['Technology', 'News'],
        ],
    ];

    $file = \Illuminate\Http\Testing\File::create('sources.json', json_encode($jsonData));

    $response = $this->actingAs($user)->post('/team-settings/import-sources', [
        'file' => $file,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('tags', ['team_id' => $team->id, 'name' => 'Technology']);
    $this->assertDatabaseHas('tags', ['team_id' => $team->id, 'name' => 'News']);

    $source = \App\Models\Source::where('team_id', $team->id)->first();
    expect($source->tags->pluck('name')->toArray())->toEqual(['Technology', 'News']);
});

test('import validates required fields', function () {
    [$user, $team] = createUserWithTeam();

    $jsonData = [
        [
            'type' => 'RSS',
            'url' => 'https://example.com/feed.xml',
            // Missing internal_name
        ],
    ];

    $file = \Illuminate\Http\Testing\File::create('sources.json', json_encode($jsonData));

    $response = $this->actingAs($user)->post('/team-settings/import-sources', [
        'file' => $file,
    ]);

    $response->assertSessionHasErrors(['sources.0.internal_name']);
});

test('import validates type enum values', function () {
    [$user, $team] = createUserWithTeam();

    $jsonData = [
        [
            'internal_name' => 'Test Source',
            'type' => 'INVALID_TYPE',
            'url' => 'https://example.com/feed.xml',
            'css_selector_title' => null,
            'css_selector_link' => null,
            'keywords' => null,
            'monitoring_interval' => 'HOURLY',
            'is_active' => true,
            'should_notify' => false,
            'auto_summarize' => true,
            'bypass_keyword_filter' => false,
            'tags' => [],
        ],
    ];

    $file = \Illuminate\Http\Testing\File::create('sources.json', json_encode($jsonData));

    $response = $this->actingAs($user)->post('/team-settings/import-sources', [
        'file' => $file,
    ]);

    $response->assertSessionHasErrors(['sources.0.type']);
});

test('import validates monitoring_interval enum values', function () {
    [$user, $team] = createUserWithTeam();

    $jsonData = [
        [
            'internal_name' => 'Test Source',
            'type' => 'RSS',
            'url' => 'https://example.com/feed.xml',
            'css_selector_title' => null,
            'css_selector_link' => null,
            'keywords' => null,
            'monitoring_interval' => 'INVALID_INTERVAL',
            'is_active' => true,
            'should_notify' => false,
            'auto_summarize' => true,
            'bypass_keyword_filter' => false,
            'tags' => [],
        ],
    ];

    $file = \Illuminate\Http\Testing\File::create('sources.json', json_encode($jsonData));

    $response = $this->actingAs($user)->post('/team-settings/import-sources', [
        'file' => $file,
    ]);

    $response->assertSessionHasErrors(['sources.0.monitoring_interval']);
});

test('import rejects invalid JSON format', function () {
    [$user, $team] = createUserWithTeam();

    $file = \Illuminate\Http\Testing\File::create('sources.json', 'invalid json content');

    $response = $this->actingAs($user)->post('/team-settings/import-sources', [
        'file' => $file,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('import rejects files with more than 1000 sources', function () {
    [$user, $team] = createUserWithTeam();

    $jsonData = [];
    for ($i = 0; $i < 1001; $i++) {
        $jsonData[] = [
            'internal_name' => "Source $i",
            'type' => 'RSS',
            'url' => "https://example.com/feed$i.xml",
            'css_selector_title' => null,
            'css_selector_link' => null,
            'keywords' => null,
            'monitoring_interval' => 'HOURLY',
            'is_active' => true,
            'should_notify' => false,
            'auto_summarize' => true,
            'bypass_keyword_filter' => false,
            'tags' => [],
        ];
    }

    $file = \Illuminate\Http\Testing\File::create('sources.json', json_encode($jsonData));

    $response = $this->actingAs($user)->post('/team-settings/import-sources', [
        'file' => $file,
    ]);

    $response->assertSessionHasErrors(['sources']);
});

test('non-owner cannot import sources', function () {
    [$owner, $team] = createUserWithTeam();
    $member = \App\Models\User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member->id, ['role' => 'member']);

    $jsonData = [
        [
            'internal_name' => 'Test Source',
            'type' => 'RSS',
            'url' => 'https://example.com/feed.xml',
            'css_selector_title' => null,
            'css_selector_link' => null,
            'keywords' => null,
            'monitoring_interval' => 'HOURLY',
            'is_active' => true,
            'should_notify' => false,
            'auto_summarize' => true,
            'bypass_keyword_filter' => false,
            'tags' => [],
        ],
    ];

    $file = \Illuminate\Http\Testing\File::create('sources.json', json_encode($jsonData));

    $this->actingAs($member)
        ->post('/team-settings/import-sources', [
            'file' => $file,
        ])
        ->assertForbidden();
});

test('import only creates sources for current team', function () {
    [$user, $team] = createUserWithTeam();

    $jsonData = [
        [
            'internal_name' => 'Test Source',
            'type' => 'RSS',
            'url' => 'https://example.com/feed.xml',
            'css_selector_title' => null,
            'css_selector_link' => null,
            'keywords' => null,
            'monitoring_interval' => 'HOURLY',
            'is_active' => true,
            'should_notify' => false,
            'auto_summarize' => true,
            'bypass_keyword_filter' => false,
            'tags' => [],
        ],
    ];

    $file = \Illuminate\Http\Testing\File::create('sources.json', json_encode($jsonData));

    $this->actingAs($user)->post('/team-settings/import-sources', [
        'file' => $file,
    ]);

    $source = \App\Models\Source::where('internal_name', 'Test Source')->first();
    expect($source->team_id)->toBe($team->id);
});
