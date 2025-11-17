<?php

use App\Models\Team;
use App\Services\KeywordFilterService;

beforeEach(function () {
    $this->service = new KeywordFilterService;
});

describe('parseKeywords', function () {
    it('parses keywords separated by newlines', function () {
        $keywords = "climate\nrenewable\nsustainability";
        $result = $this->service->parseKeywords($keywords);

        expect($result)->toBe(['climate', 'renewable', 'sustainability']);
    });

    it('trims whitespace from keywords', function () {
        $keywords = "  climate  \n  renewable  \n  sustainability  ";
        $result = $this->service->parseKeywords($keywords);

        expect($result)->toBe(['climate', 'renewable', 'sustainability']);
    });

    it('filters out empty lines', function () {
        $keywords = "climate\n\nrenewable\n\n\nsustainability\n";
        $result = $this->service->parseKeywords($keywords);

        expect($result)->toBe(['climate', 'renewable', 'sustainability']);
    });

    it('returns empty array for null input', function () {
        $result = $this->service->parseKeywords(null);

        expect($result)->toBe([]);
    });

    it('returns empty array for empty string', function () {
        $result = $this->service->parseKeywords('');

        expect($result)->toBe([]);
    });
});

describe('containsAnyKeyword', function () {
    it('finds keyword in text (case-insensitive)', function () {
        $result = $this->service->containsAnyKeyword('Climate Change Report', ['climate']);

        expect($result)->toBeTrue();
    });

    it('finds keyword with different case', function () {
        $result = $this->service->containsAnyKeyword('CLIMATE change', ['climate']);

        expect($result)->toBeTrue();
    });

    it('finds partial match within word', function () {
        $result = $this->service->containsAnyKeyword('Renewable Energy Source', ['renew']);

        expect($result)->toBeTrue();
    });

    it('matches any keyword from list', function () {
        $result = $this->service->containsAnyKeyword('Solar Power News', ['wind', 'solar', 'hydro']);

        expect($result)->toBeTrue();
    });

    it('returns false when no keywords match', function () {
        $result = $this->service->containsAnyKeyword('Technology Update', ['climate', 'renewable']);

        expect($result)->toBeFalse();
    });

    it('returns false for empty keywords array', function () {
        $result = $this->service->containsAnyKeyword('Climate Change', []);

        expect($result)->toBeFalse();
    });
});

describe('shouldIncludePost', function () {
    it('includes post when no keywords are set', function () {
        $team = Team::factory()->make([
            'positive_keywords' => null,
            'negative_keywords' => null,
        ]);

        $result = $this->service->shouldIncludePost('Any Title', $team);

        expect($result)->toBeTrue();
    });

    it('includes post when title contains positive keyword', function () {
        $team = Team::factory()->make([
            'positive_keywords' => "climate\nrenewable",
            'negative_keywords' => null,
        ]);

        $result = $this->service->shouldIncludePost('Climate Change Report', $team);

        expect($result)->toBeTrue();
    });

    it('excludes post when title does not contain any positive keyword', function () {
        $team = Team::factory()->make([
            'positive_keywords' => "climate\nrenewable",
            'negative_keywords' => null,
        ]);

        $result = $this->service->shouldIncludePost('Technology Update', $team);

        expect($result)->toBeFalse();
    });

    it('excludes post when title contains negative keyword', function () {
        $team = Team::factory()->make([
            'positive_keywords' => null,
            'negative_keywords' => "sponsored\nadvertisement",
        ]);

        $result = $this->service->shouldIncludePost('Sponsored Content', $team);

        expect($result)->toBeFalse();
    });

    it('includes post when title does not contain any negative keyword', function () {
        $team = Team::factory()->make([
            'positive_keywords' => null,
            'negative_keywords' => "sponsored\nadvertisement",
        ]);

        $result = $this->service->shouldIncludePost('Climate News', $team);

        expect($result)->toBeTrue();
    });

    it('negative keywords take priority over positive keywords', function () {
        $team = Team::factory()->make([
            'positive_keywords' => "climate\nnews",
            'negative_keywords' => 'sponsored',
        ]);

        // Title contains both positive keyword (climate) and negative keyword (sponsored)
        $result = $this->service->shouldIncludePost('Sponsored Climate Report', $team);

        expect($result)->toBeFalse();
    });

    it('includes post matching positive when not matching negative', function () {
        $team = Team::factory()->make([
            'positive_keywords' => 'climate',
            'negative_keywords' => 'sponsored',
        ]);

        $result = $this->service->shouldIncludePost('Climate Change Report', $team);

        expect($result)->toBeTrue();
    });
});

describe('filterPosts', function () {
    it('returns all posts when no keywords are set', function () {
        $team = Team::factory()->make([
            'positive_keywords' => null,
            'negative_keywords' => null,
        ]);

        $posts = [
            ['title' => 'Post 1', 'url' => 'http://example.com/1'],
            ['title' => 'Post 2', 'url' => 'http://example.com/2'],
        ];

        $result = $this->service->filterPosts($posts, $team);

        expect($result)->toHaveCount(2);
    });

    it('filters posts by positive keywords', function () {
        $team = Team::factory()->make([
            'positive_keywords' => 'climate',
            'negative_keywords' => null,
        ]);

        $posts = [
            ['title' => 'Climate Report', 'url' => 'http://example.com/1'],
            ['title' => 'Tech News', 'url' => 'http://example.com/2'],
            ['title' => 'Climate Update', 'url' => 'http://example.com/3'],
        ];

        $result = $this->service->filterPosts($posts, $team);

        expect($result)->toHaveCount(2);
        expect($result[0]['title'])->toBe('Climate Report');
        expect($result[1]['title'])->toBe('Climate Update');
    });

    it('filters out posts with negative keywords', function () {
        $team = Team::factory()->make([
            'positive_keywords' => null,
            'negative_keywords' => 'sponsored',
        ]);

        $posts = [
            ['title' => 'Good Article', 'url' => 'http://example.com/1'],
            ['title' => 'Sponsored Post', 'url' => 'http://example.com/2'],
            ['title' => 'Another Article', 'url' => 'http://example.com/3'],
        ];

        $result = $this->service->filterPosts($posts, $team);

        expect($result)->toHaveCount(2);
        expect($result[0]['title'])->toBe('Good Article');
        expect($result[1]['title'])->toBe('Another Article');
    });
});

describe('filterSourceItems', function () {
    it('returns all items when no keywords are set', function () {
        $team = Team::factory()->make([
            'positive_keywords' => null,
            'negative_keywords' => null,
        ]);

        $items = [
            ['uri' => 'http://example.com/1', 'title' => 'Post 1'],
            ['uri' => 'http://example.com/2', 'title' => 'Post 2'],
        ];

        $result = $this->service->filterSourceItems($items, $team);

        expect($result)->toHaveCount(2);
    });

    it('filters RSS items by title', function () {
        $team = Team::factory()->make([
            'positive_keywords' => 'climate',
            'negative_keywords' => null,
        ]);

        $items = [
            ['uri' => 'http://example.com/1', 'title' => 'Climate News'],
            ['uri' => 'http://example.com/2', 'title' => 'Tech Update'],
        ];

        $result = $this->service->filterSourceItems($items, $team);

        expect($result)->toHaveCount(1);
        expect($result[0]['title'])->toBe('Climate News');
    });

    it('preserves titles fetched for items without titles', function () {
        $team = Team::factory()->make([
            'positive_keywords' => 'climate',
            'negative_keywords' => null,
        ]);

        $service = Mockery::mock(KeywordFilterService::class)->makePartial();
        $service->shouldReceive('fetchTitleFromUrl')
            ->with('http://example.com/1')
            ->andReturn('Climate Report');

        $items = [
            ['uri' => 'http://example.com/1'],
        ];

        $result = $service->filterSourceItems($items, $team);

        expect($result)->toHaveCount(1);
        expect($result[0]['title'])->toBe('Climate Report');
    });

    it('excludes items with negative keywords', function () {
        $team = Team::factory()->make([
            'positive_keywords' => null,
            'negative_keywords' => 'sponsored',
        ]);

        $items = [
            ['uri' => 'http://example.com/1', 'title' => 'Good Post'],
            ['uri' => 'http://example.com/2', 'title' => 'Sponsored Content'],
        ];

        $result = $this->service->filterSourceItems($items, $team);

        expect($result)->toHaveCount(1);
        expect($result[0]['title'])->toBe('Good Post');
    });
});
