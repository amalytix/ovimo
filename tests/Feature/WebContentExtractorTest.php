<?php

use App\Services\WebContentExtractor;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->extractor = new WebContentExtractor;
});

it('extracts article content and converts to markdown', function () {
    Http::fake([
        'https://example.com/article' => Http::response(<<<'HTML'
            <!DOCTYPE html>
            <html>
            <head><title>Test Article</title></head>
            <body>
                <nav>Navigation here</nav>
                <article>
                    <h1>Main Article Title</h1>
                    <p>This is the first paragraph of the article. It contains important information that should be extracted.</p>
                    <p>This is another paragraph with more content. The readability algorithm should identify this as the main content.</p>
                </article>
                <footer>Footer content here</footer>
            </body>
            </html>
        HTML, 200),
    ]);

    $result = $this->extractor->extractArticleAsMarkdown('https://example.com/article');

    expect($result)
        ->toContain('Main Article Title')
        ->toContain('first paragraph')
        ->toContain('another paragraph')
        ->not->toContain('Navigation here')
        ->not->toContain('Footer content here');
});

it('returns error message when URL cannot be fetched', function () {
    Http::fake([
        'https://example.com/not-found' => Http::response('Not Found', 404),
    ]);

    $result = $this->extractor->extractArticleAsMarkdown('https://example.com/not-found');

    expect($result)->toBe('The full content could not be fetched.');
});

it('returns error message when page has no extractable content', function () {
    Http::fake([
        'https://example.com/empty' => Http::response(<<<'HTML'
            <!DOCTYPE html>
            <html>
            <head><title>Empty Page</title></head>
            <body>
            </body>
            </html>
        HTML, 200),
    ]);

    $result = $this->extractor->extractArticleAsMarkdown('https://example.com/empty');

    expect($result)->toBe('The full content could not be fetched.');
});

it('returns error message when HTTP request times out', function () {
    Http::fake([
        'https://example.com/slow' => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        },
    ]);

    $result = $this->extractor->extractArticleAsMarkdown('https://example.com/slow');

    expect($result)->toBe('The full content could not be fetched.');
});

it('converts HTML formatting to markdown', function () {
    Http::fake([
        'https://example.com/formatted' => Http::response(<<<'HTML'
            <!DOCTYPE html>
            <html>
            <head><title>Formatted Article</title></head>
            <body>
                <article>
                    <h1>Article with Formatting</h1>
                    <p>This paragraph has <strong>bold text</strong> and <em>italic text</em>.</p>
                    <p>Here is a <a href="https://example.com">link</a> in the content.</p>
                    <ul>
                        <li>First item</li>
                        <li>Second item</li>
                    </ul>
                </article>
            </body>
            </html>
        HTML, 200),
    ]);

    $result = $this->extractor->extractArticleAsMarkdown('https://example.com/formatted');

    expect($result)
        ->toContain('**bold text**')
        ->toContain('*italic text*')
        ->toContain('[link](https://example.com)')
        ->toContain('- First item')
        ->toContain('- Second item');
});
