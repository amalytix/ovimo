<?php

namespace App\Services;

use fivefilters\Readability\Configuration;
use fivefilters\Readability\ParseException;
use fivefilters\Readability\Readability;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use League\HTMLToMarkdown\HtmlConverter;

class WebContentExtractor
{
    private HtmlConverter $converter;

    public function __construct()
    {
        $this->converter = new HtmlConverter;
        $this->converter->getConfig()->setOption('strip_tags', true);
    }

    public function extractArticleAsMarkdown(string $uri): string
    {
        try {
            $html = $this->fetchHtml($uri);
            $articleHtml = $this->extractArticle($html, $uri);
            $markdown = $this->convertToMarkdown($articleHtml);

            return $markdown;
        } catch (\Exception $e) {
            Log::warning('Failed to extract article content', [
                'uri' => $uri,
                'error' => $e->getMessage(),
            ]);

            return 'The full content could not be fetched.';
        }
    }

    private function fetchHtml(string $uri): string
    {
        $response = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; ContentExtractor/1.0)',
            ])
            ->get($uri);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to fetch URL: HTTP {$response->status()}");
        }

        return $response->body();
    }

    private function extractArticle(string $html, string $uri): string
    {
        $configuration = new Configuration([
            'OriginalURL' => $uri,
            'FixRelativeURLs' => true,
            'SubstituteEntities' => true,
        ]);

        $readability = new Readability($configuration);

        try {
            $readability->parse($html);
        } catch (ParseException $e) {
            throw new \RuntimeException("Failed to parse article: {$e->getMessage()}");
        }

        $content = $readability->getContent();

        if (empty($content)) {
            throw new \RuntimeException('No article content found');
        }

        return $content;
    }

    private function convertToMarkdown(string $html): string
    {
        $markdown = $this->converter->convert($html);

        return $this->normalizeBlankLines($markdown);
    }

    private function normalizeBlankLines(string $text): string
    {
        // Replace multiple consecutive blank lines with a single blank line
        return preg_replace("/\n{3,}/", "\n\n", $text);
    }
}
