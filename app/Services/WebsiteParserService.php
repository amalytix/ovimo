<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class WebsiteParserService
{
    /**
     * Fetch HTML content from a URL.
     */
    public function fetchHtml(string $url): string
    {
        $response = Http::timeout(30)
            ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36')
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to fetch URL: {$url}. Status: {$response->status()}");
        }

        return $response->body();
    }

    /**
     * Extract posts from HTML using CSS selectors.
     *
     * @return array<int, array{title: string, link: string}>
     */
    public function extractPosts(string $html, string $cssSelectorTitle, string $cssSelectorLink, string $baseUrl = ''): array
    {
        $crawler = new Crawler($html);
        $posts = [];

        try {
            $titles = $crawler->filter($cssSelectorTitle)->each(fn (Crawler $node) => trim($node->text()));
            $links = $crawler->filter($cssSelectorLink)->each(function (Crawler $node) use ($baseUrl) {
                $href = $node->attr('href') ?? '';

                // Handle relative URLs
                if ($href && ! str_starts_with($href, 'http')) {
                    $parsedBase = parse_url($baseUrl);
                    $scheme = $parsedBase['scheme'] ?? 'https';
                    $host = $parsedBase['host'] ?? '';

                    if (str_starts_with($href, '//')) {
                        $href = $scheme.':'.$href;
                    } elseif (str_starts_with($href, '/')) {
                        $href = $scheme.'://'.$host.$href;
                    } else {
                        $href = $scheme.'://'.$host.'/'.$href;
                    }
                }

                return $href;
            });

            $count = min(count($titles), count($links));
            for ($i = 0; $i < $count; $i++) {
                $posts[] = [
                    'title' => $titles[$i],
                    'link' => $links[$i],
                ];
            }
        } catch (\Exception $e) {
            Log::error('Error extracting posts from HTML', [
                'error' => $e->getMessage(),
                'css_selector_title' => $cssSelectorTitle,
                'css_selector_link' => $cssSelectorLink,
            ]);
        }

        return $posts;
    }

    /**
     * Filter posts by keywords (case-insensitive).
     *
     * @param  array<int, array{title: string, link: string}>  $posts
     * @param  string  $keywordsString  Comma-separated keywords
     * @return array<int, array{title: string, link: string}>
     */
    public function filterPostsByKeywords(array $posts, string $keywordsString): array
    {
        if (empty(trim($keywordsString))) {
            return $posts;
        }

        $keywords = array_map(
            fn ($keyword) => strtolower(trim($keyword)),
            explode(',', $keywordsString)
        );

        // Remove empty keywords
        $keywords = array_filter($keywords, fn ($keyword) => $keyword !== '');

        if (empty($keywords)) {
            return $posts;
        }

        return array_values(array_filter($posts, function ($post) use ($keywords) {
            $titleLower = strtolower($post['title']);

            foreach ($keywords as $keyword) {
                if (str_contains($titleLower, $keyword)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * Parse a website source and return posts.
     *
     * @return array<int, array{title: string, link: string}>
     */
    public function parse(string $url, string $cssSelectorTitle, string $cssSelectorLink, ?string $keywords = null, int $maxPosts = 100): array
    {
        $html = $this->fetchHtml($url);
        $posts = $this->extractPosts($html, $cssSelectorTitle, $cssSelectorLink, $url);

        if ($keywords) {
            $posts = $this->filterPostsByKeywords($posts, $keywords);
        }

        return array_slice($posts, 0, $maxPosts);
    }
}
