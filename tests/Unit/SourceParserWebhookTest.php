<?php

use App\Models\Source;
use App\Services\SourceParser;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->parser = new SourceParser;
});

describe('parseWebhook', function () {
    it('successfully parses webhook response with valid data', function () {
        $source = Source::factory()->make([
            'type' => 'WEBHOOK',
            'url' => 'https://example.com/webhook',
            'keywords' => 'amvisor,amalytix,insightleap',
        ]);

        Http::fake([
            'example.com/*' => Http::response([
                'data' => [
                    [
                        'title' => 'Test Post 1',
                        'url' => 'https://example.com/post/1',
                        'subreddit' => 'r/test',
                        'created' => 1741793734,
                    ],
                    [
                        'title' => 'Test Post 2',
                        'url' => 'https://example.com/post/2',
                        'id' => 'abc123',
                    ],
                ],
            ], 200),
        ]);

        $result = $this->parser->parse($source->url, $source->type, null, $source);

        expect($result)->toHaveCount(2)
            ->and($result[0])->toHaveKeys(['uri', 'title', 'metadata'])
            ->and($result[0]['uri'])->toBe('https://example.com/post/1')
            ->and($result[0]['title'])->toBe('Test Post 1')
            ->and($result[0]['metadata'])->toHaveKey('subreddit')
            ->and($result[1]['uri'])->toBe('https://example.com/post/2');
    });

    it('sends keywords as JSON array in POST request', function () {
        $source = Source::factory()->make([
            'type' => 'WEBHOOK',
            'url' => 'https://example.com/webhook',
            'keywords' => 'keyword1, keyword2 , keyword3',
        ]);

        Http::fake([
            'example.com/*' => Http::response(['data' => []], 200),
        ]);

        $this->parser->parse($source->url, $source->type, null, $source);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/webhook'
                && $request->method() === 'POST'
                && $request->data() === ['keywords' => ['keyword1', 'keyword2', 'keyword3']];
        });
    });

    it('throws exception on HTTP error', function () {
        $source = Source::factory()->make([
            'type' => 'WEBHOOK',
            'url' => 'https://example.com/webhook',
            'keywords' => 'test',
        ]);

        Http::fake([
            'example.com/*' => Http::response([], 500),
        ]);

        expect(fn () => $this->parser->parse($source->url, $source->type, null, $source))
            ->toThrow(\RuntimeException::class);
    });

    it('returns empty array for missing data field', function () {
        $source = Source::factory()->make([
            'type' => 'WEBHOOK',
            'url' => 'https://example.com/webhook',
            'keywords' => 'test',
        ]);

        Http::fake([
            'example.com/*' => Http::response(['items' => []], 200),
        ]);

        $result = $this->parser->parse($source->url, $source->type, null, $source);

        expect($result)->toBeEmpty();
    });

    it('skips items without required fields', function () {
        $source = Source::factory()->make([
            'type' => 'WEBHOOK',
            'url' => 'https://example.com/webhook',
            'keywords' => 'test',
        ]);

        Http::fake([
            'example.com/*' => Http::response([
                'data' => [
                    ['title' => 'Missing URL'],
                    ['url' => 'https://example.com/post/1'],
                    ['title' => 'Valid', 'url' => 'https://example.com/post/2'],
                ],
            ], 200),
        ]);

        $result = $this->parser->parse($source->url, $source->type, null, $source);

        expect($result)->toHaveCount(1)
            ->and($result[0]['title'])->toBe('Valid');
    });
});
