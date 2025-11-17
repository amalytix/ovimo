<?php

use App\Jobs\MonitorSource;
use App\Models\Source;
use App\Services\OpenAIService;
use App\Services\WebsiteParserService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('authenticated users can create a website source', function () {
    Queue::fake();

    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/sources', [
        'internal_name' => 'Hacker News',
        'type' => 'WEBSITE',
        'url' => 'https://news.ycombinator.com/',
        'css_selector_title' => '.titleline a',
        'css_selector_link' => '.titleline a',
        'keywords' => 'Amazon, Vendor Central',
        'monitoring_interval' => 'HOURLY',
        'is_active' => true,
        'should_notify' => false,
        'auto_summarize' => false,
    ]);

    $response->assertRedirect('/sources');

    $this->assertDatabaseHas('sources', [
        'team_id' => $team->id,
        'internal_name' => 'Hacker News',
        'type' => 'WEBSITE',
        'url' => 'https://news.ycombinator.com/',
        'css_selector_title' => '.titleline a',
        'css_selector_link' => '.titleline a',
        'keywords' => 'amazon,vendor central', // normalized to lowercase
    ]);

    Queue::assertPushed(MonitorSource::class);
});

test('website source requires css selectors', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/sources', [
        'internal_name' => 'Test Website',
        'type' => 'WEBSITE',
        'url' => 'https://example.com/',
        'monitoring_interval' => 'HOURLY',
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors(['css_selector_title', 'css_selector_link']);
});

test('rss source does not require css selectors', function () {
    Queue::fake();

    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/sources', [
        'internal_name' => 'RSS Source',
        'type' => 'RSS',
        'url' => 'https://example.com/feed.xml',
        'monitoring_interval' => 'HOURLY',
        'is_active' => true,
    ]);

    $response->assertRedirect('/sources');
});

test('keywords are normalized on save', function () {
    Queue::fake();

    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/sources', [
        'internal_name' => 'Keyword Test',
        'type' => 'WEBSITE',
        'url' => 'https://example.com/',
        'css_selector_title' => '.title',
        'css_selector_link' => '.link',
        'keywords' => '  Amazon  ,  Vendor Central  ,  SELLER central  ',
        'monitoring_interval' => 'HOURLY',
        'is_active' => false,
    ]);

    $response->assertRedirect('/sources');

    // Keywords should be trimmed and lowercased
    $this->assertDatabaseHas('sources', [
        'keywords' => 'amazon,vendor central,seller central',
    ]);
});

test('test extraction endpoint returns posts', function () {
    Http::fake([
        'https://example.com/' => Http::response('
            <html>
            <body>
                <div class="post">
                    <a href="/post/1" class="title">First Post About Amazon</a>
                </div>
                <div class="post">
                    <a href="/post/2" class="title">Second Post</a>
                </div>
                <div class="post">
                    <a href="/post/3" class="title">Third Post About Vendor Central</a>
                </div>
            </body>
            </html>
        '),
    ]);

    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->postJson('/sources/test-extraction', [
        'url' => 'https://example.com/',
        'css_selector_title' => '.post .title',
        'css_selector_link' => '.post .title',
        'keywords' => 'amazon,vendor central',
    ]);

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'posts'); // Only posts with keywords
    $response->assertJsonPath('posts.0.title', 'First Post About Amazon');
    $response->assertJsonPath('posts.1.title', 'Third Post About Vendor Central');
});

test('test extraction without keywords returns all posts', function () {
    Http::fake([
        'https://example.com/' => Http::response('
            <html>
            <body>
                <div class="post">
                    <a href="/post/1" class="title">First Post</a>
                </div>
                <div class="post">
                    <a href="/post/2" class="title">Second Post</a>
                </div>
            </body>
            </html>
        '),
    ]);

    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->postJson('/sources/test-extraction', [
        'url' => 'https://example.com/',
        'css_selector_title' => '.post .title',
        'css_selector_link' => '.post .title',
        'keywords' => '',
    ]);

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'posts');
});

test('test extraction validates required fields', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->postJson('/sources/test-extraction', [
        'url' => 'https://example.com/',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['css_selector_title', 'css_selector_link']);
});

test('analyze webpage endpoint calls openai service', function () {
    Http::fake([
        'https://example.com/' => Http::response('<html><body><div class="post"><a class="title" href="/1">Test</a></div></body></html>'),
    ]);

    $mockOpenAI = Mockery::mock(OpenAIService::class);
    $mockOpenAI->shouldReceive('analyzeWebpage')
        ->once()
        ->andReturn([
            'css_selector_title' => '.post .title',
            'css_selector_link' => '.post .title',
            'input_tokens' => 100,
            'output_tokens' => 50,
            'total_tokens' => 150,
            'model' => 'gpt-5.1',
        ]);
    $mockOpenAI->shouldReceive('trackUsage')->once();

    $this->app->instance(OpenAIService::class, $mockOpenAI);

    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->postJson('/sources/analyze-webpage', [
        'url' => 'https://example.com/',
    ]);

    $response->assertSuccessful();
    $response->assertJson([
        'css_selector_title' => '.post .title',
        'css_selector_link' => '.post .title',
    ]);
});

test('website parser filters posts by keywords case insensitively', function () {
    $parser = new WebsiteParserService;

    $posts = [
        ['title' => 'Amazon launches new service', 'link' => 'http://example.com/1'],
        ['title' => 'Google updates search', 'link' => 'http://example.com/2'],
        ['title' => 'AMAZON Web Services news', 'link' => 'http://example.com/3'],
        ['title' => 'Vendor Central API changes', 'link' => 'http://example.com/4'],
    ];

    $filtered = $parser->filterPostsByKeywords($posts, 'amazon, vendor central');

    expect($filtered)->toHaveCount(3);
    expect($filtered[0]['title'])->toBe('Amazon launches new service');
    expect($filtered[1]['title'])->toBe('AMAZON Web Services news');
    expect($filtered[2]['title'])->toBe('Vendor Central API changes');
});

test('website parser handles relative urls', function () {
    $parser = new WebsiteParserService;

    $html = '
        <html>
        <body>
            <div class="post"><a href="/relative/path">Post 1</a></div>
            <div class="post"><a href="//cdn.example.com/post2">Post 2</a></div>
            <div class="post"><a href="https://external.com/post3">Post 3</a></div>
        </body>
        </html>
    ';

    $posts = $parser->extractPosts($html, '.post a', '.post a', 'https://news.ycombinator.com/');

    expect($posts)->toHaveCount(3);
    expect($posts[0]['link'])->toBe('https://news.ycombinator.com/relative/path');
    expect($posts[1]['link'])->toBe('https://cdn.example.com/post2');
    expect($posts[2]['link'])->toBe('https://external.com/post3');
});

test('authenticated users can update website source', function () {
    [$user, $team] = createUserWithTeam();

    $source = Source::factory()->create([
        'team_id' => $team->id,
        'type' => 'WEBSITE',
        'css_selector_title' => '.old-title',
        'css_selector_link' => '.old-link',
        'keywords' => 'old,keywords',
    ]);

    $response = $this->actingAs($user)->put("/sources/{$source->id}", [
        'internal_name' => 'Updated Source',
        'type' => 'WEBSITE',
        'url' => 'https://updated.com/',
        'css_selector_title' => '.new-title',
        'css_selector_link' => '.new-link',
        'keywords' => '  New Keyword  ,  Another  ',
        'monitoring_interval' => 'DAILY',
        'is_active' => true,
        'should_notify' => false,
        'auto_summarize' => false,
    ]);

    $response->assertRedirect('/sources');

    $this->assertDatabaseHas('sources', [
        'id' => $source->id,
        'css_selector_title' => '.new-title',
        'css_selector_link' => '.new-link',
        'keywords' => 'new keyword,another', // normalized
    ]);
});

test('guests cannot access analyze webpage endpoint', function () {
    $response = $this->postJson('/sources/analyze-webpage', [
        'url' => 'https://example.com/',
    ]);

    $response->assertUnauthorized();
});

test('guests cannot access test extraction endpoint', function () {
    $response = $this->postJson('/sources/test-extraction', [
        'url' => 'https://example.com/',
        'css_selector_title' => '.title',
        'css_selector_link' => '.link',
    ]);

    $response->assertUnauthorized();
});
