<?php

use App\Services\SourceParser;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.rss.max_entries' => 10]);
});

it('limits RSS entries to configured max', function () {
    Http::fake([
        'https://example.com/feed.xml' => Http::response(createRssFeed(50), 200),
    ]);

    $parser = new SourceParser;
    $items = $parser->parse('https://example.com/feed.xml', 'RSS');

    expect($items)->toHaveCount(10);
});

it('respects custom max entries parameter', function () {
    Http::fake([
        'https://example.com/feed.xml' => Http::response(createRssFeed(50), 200),
    ]);

    $parser = new SourceParser;
    $items = $parser->parse('https://example.com/feed.xml', 'RSS', 5);

    expect($items)->toHaveCount(5);
});

it('returns fewer items when feed has less than max', function () {
    Http::fake([
        'https://example.com/feed.xml' => Http::response(createRssFeed(3), 200),
    ]);

    $parser = new SourceParser;
    $items = $parser->parse('https://example.com/feed.xml', 'RSS');

    expect($items)->toHaveCount(3);
});

it('parses RSS feed items correctly', function () {
    Http::fake([
        'https://example.com/feed.xml' => Http::response(createRssFeed(2), 200),
    ]);

    $parser = new SourceParser;
    $items = $parser->parse('https://example.com/feed.xml', 'RSS', 2);

    expect($items[0])->toHaveKeys(['uri', 'title']);
    expect($items[0]['uri'])->toBe('https://example.com/article-1');
    expect($items[0]['title'])->toBe('Article 1');
});

it('parses Atom feed entries correctly', function () {
    Http::fake([
        'https://example.com/feed.xml' => Http::response(createAtomFeed(5), 200),
    ]);

    $parser = new SourceParser;
    $items = $parser->parse('https://example.com/feed.xml', 'RSS', 3);

    expect($items)->toHaveCount(3);
    expect($items[0]['uri'])->toBe('https://example.com/entry-1');
    expect($items[0]['title'])->toBe('Entry 1');
});

it('handles RSS feeds with large content efficiently', function () {
    Http::fake([
        'https://example.com/feed.xml' => Http::response(createLargeRssFeed(100), 200),
    ]);

    $startMemory = memory_get_usage();

    $parser = new SourceParser;
    $items = $parser->parse('https://example.com/feed.xml', 'RSS', 10);

    $memoryUsed = memory_get_usage() - $startMemory;

    expect($items)->toHaveCount(10);
    expect($memoryUsed)->toBeLessThan(50 * 1024 * 1024); // Less than 50MB
});

it('limits XML sitemap entries', function () {
    Http::fake([
        'https://example.com/sitemap.xml' => Http::response(createXmlSitemap(50), 200),
    ]);

    $parser = new SourceParser;
    $items = $parser->parse('https://example.com/sitemap.xml', 'XML_SITEMAP', 15);

    expect($items)->toHaveCount(15);
});

it('throws exception for failed HTTP request', function () {
    Http::fake([
        'https://example.com/feed.xml' => Http::response('Not Found', 404),
    ]);

    $parser = new SourceParser;

    expect(fn () => $parser->parse('https://example.com/feed.xml', 'RSS'))
        ->toThrow(\RuntimeException::class, 'Failed to fetch URL');
});

it('throws exception for invalid feed format', function () {
    Http::fake([
        'https://example.com/feed.xml' => Http::response('<invalid><xml/></invalid>', 200),
    ]);

    $parser = new SourceParser;

    expect(fn () => $parser->parse('https://example.com/feed.xml', 'RSS'))
        ->toThrow(\RuntimeException::class, 'Unable to detect feed type');
});

it('throws exception for unknown source type', function () {
    $parser = new SourceParser;

    expect(fn () => $parser->parse('https://example.com/feed.xml', 'UNKNOWN'))
        ->toThrow(\InvalidArgumentException::class, 'Unknown source type');
});

it('uses environment configuration by default', function () {
    config(['services.rss.max_entries' => 5]);

    Http::fake([
        'https://example.com/feed.xml' => Http::response(createRssFeed(20), 200),
    ]);

    $parser = new SourceParser;
    $items = $parser->parse('https://example.com/feed.xml', 'RSS');

    expect($items)->toHaveCount(5);
});

it('cleans up temporary files after parsing', function () {
    Http::fake([
        'https://example.com/feed.xml' => Http::response(createRssFeed(5), 200),
    ]);

    $tempDir = sys_get_temp_dir();
    $beforeFiles = glob($tempDir.'/rss_*');

    $parser = new SourceParser;
    $parser->parse('https://example.com/feed.xml', 'RSS');

    $afterFiles = glob($tempDir.'/rss_*');

    expect(count($afterFiles))->toBe(count($beforeFiles));
});

it('handles real large Amazon Science RSS feed efficiently', function () {
    // Use the actual large RSS file from docs directory
    $largeFeedContent = file_get_contents(base_path('docs/large-rss-file.xml'));

    Http::fake([
        'https://www.amazon.science/index.rss' => Http::response($largeFeedContent, 200),
    ]);

    $memoryBefore = memory_get_usage(true);

    $parser = new SourceParser;
    $items = $parser->parse('https://www.amazon.science/index.rss', 'RSS', 10);

    $memoryAfter = memory_get_usage(true);
    $memoryUsed = $memoryAfter - $memoryBefore;

    // Should return exactly 10 items
    expect($items)->toHaveCount(10);

    // Memory usage should be reasonable (less than 50MB for a 15MB file)
    expect($memoryUsed)->toBeLessThan(50 * 1024 * 1024);

    // First item should have proper structure
    expect($items[0])->toHaveKeys(['uri', 'title']);
    expect($items[0]['uri'])->toStartWith('https://www.amazon.science/');
    expect($items[0]['title'])->not->toBeEmpty();

    // All items should have valid URLs
    foreach ($items as $item) {
        expect($item['uri'])->toBeUrl();
        expect($item['title'])->toBeString();
    }
});

/**
 * Helper function to create an RSS feed with specified number of items.
 */
function createRssFeed(int $itemCount): string
{
    $items = '';
    for ($i = 1; $i <= $itemCount; $i++) {
        $items .= <<<XML
        <item>
            <title>Article {$i}</title>
            <link>https://example.com/article-{$i}</link>
            <description>Description for article {$i}</description>
            <pubDate>Mon, 01 Jan 2024 12:00:00 GMT</pubDate>
        </item>
        XML;
    }

    return <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <rss version="2.0">
        <channel>
            <title>Test Feed</title>
            <link>https://example.com</link>
            <description>Test RSS Feed</description>
            {$items}
        </channel>
    </rss>
    XML;
}

/**
 * Helper function to create an Atom feed with specified number of entries.
 */
function createAtomFeed(int $entryCount): string
{
    $entries = '';
    for ($i = 1; $i <= $entryCount; $i++) {
        $entries .= <<<XML
        <entry>
            <title>Entry {$i}</title>
            <link href="https://example.com/entry-{$i}" />
            <id>urn:uuid:{$i}</id>
            <updated>2024-01-01T12:00:00Z</updated>
            <summary>Summary for entry {$i}</summary>
        </entry>
        XML;
    }

    return <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <feed xmlns="http://www.w3.org/2005/Atom">
        <title>Test Atom Feed</title>
        <link href="https://example.com/" />
        <updated>2024-01-01T12:00:00Z</updated>
        <id>urn:uuid:test-feed</id>
        {$entries}
    </feed>
    XML;
}

/**
 * Helper function to create an RSS feed with large content (like real-world feeds).
 */
function createLargeRssFeed(int $itemCount): string
{
    $items = '';
    $largeContent = str_repeat('<p>This is a large paragraph of content that simulates real-world RSS feeds with embedded HTML content. </p>', 100);

    for ($i = 1; $i <= $itemCount; $i++) {
        $items .= <<<XML
        <item>
            <title>Article {$i}</title>
            <link>https://example.com/article-{$i}</link>
            <description>Description for article {$i}</description>
            <pubDate>Mon, 01 Jan 2024 12:00:00 GMT</pubDate>
            <content:encoded><![CDATA[{$largeContent}]]></content:encoded>
        </item>
        XML;
    }

    return <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <rss xmlns:content="http://purl.org/rss/1.0/modules/content/" version="2.0">
        <channel>
            <title>Test Feed</title>
            <link>https://example.com</link>
            <description>Test RSS Feed with large content</description>
            {$items}
        </channel>
    </rss>
    XML;
}

/**
 * Helper function to create an XML sitemap with specified number of URLs.
 */
function createXmlSitemap(int $urlCount): string
{
    $urls = '';
    for ($i = 1; $i <= $urlCount; $i++) {
        $urls .= <<<XML
        <url>
            <loc>https://example.com/page-{$i}</loc>
            <lastmod>2024-01-01T12:00:00Z</lastmod>
        </url>
        XML;
    }

    return <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
        {$urls}
    </urlset>
    XML;
}
