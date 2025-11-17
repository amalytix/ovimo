<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\Http;

class KeywordFilterService
{
    /**
     * Parse keywords string (one per line) into an array.
     *
     * @return array<string>
     */
    public function parseKeywords(?string $keywords): array
    {
        if (empty($keywords)) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode("\n", $keywords)),
            fn ($keyword) => $keyword !== ''
        ));
    }

    /**
     * Check if text contains any of the given keywords (case-insensitive).
     *
     * @param  array<string>  $keywords
     */
    public function containsAnyKeyword(string $text, array $keywords): bool
    {
        if (empty($keywords)) {
            return false;
        }

        $lowerText = mb_strtolower($text);

        foreach ($keywords as $keyword) {
            if (str_contains($lowerText, mb_strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a post should be included based on team keyword filters.
     * Negative keywords take priority (exclude first), then check positive keywords.
     */
    public function shouldIncludePost(string $title, Team $team): bool
    {
        $positiveKeywords = $this->parseKeywords($team->positive_keywords);
        $negativeKeywords = $this->parseKeywords($team->negative_keywords);

        // If negative keywords are set and title contains any, exclude the post
        if (! empty($negativeKeywords) && $this->containsAnyKeyword($title, $negativeKeywords)) {
            return false;
        }

        // If positive keywords are set, title must contain at least one
        if (! empty($positiveKeywords)) {
            return $this->containsAnyKeyword($title, $positiveKeywords);
        }

        // No positive keywords set, include by default
        return true;
    }

    /**
     * Filter an array of posts based on team keyword filters.
     *
     * @param  array<array{title: string, url: string}>  $posts
     * @return array<array{title: string, url: string}>
     */
    public function filterPosts(array $posts, Team $team): array
    {
        $positiveKeywords = $this->parseKeywords($team->positive_keywords);
        $negativeKeywords = $this->parseKeywords($team->negative_keywords);

        // No filtering needed if both lists are empty
        if (empty($positiveKeywords) && empty($negativeKeywords)) {
            return $posts;
        }

        return array_values(array_filter($posts, function ($post) use ($team) {
            $title = $post['title'] ?? '';

            return $this->shouldIncludePost($title, $team);
        }));
    }

    /**
     * Fetch the HTML title from a URL.
     */
    public function fetchTitleFromUrl(string $url): ?string
    {
        try {
            $response = Http::timeout(10)->get($url);

            if (! $response->successful()) {
                return null;
            }

            $html = $response->body();

            // Extract title from <title> tag
            if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
                return trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Filter source items with keyword filtering support.
     * For items without titles (like XML sitemaps), fetches the title from HTML.
     *
     * @param  array<array{uri: string, title?: string}>  $items
     * @return array<array{uri: string, title?: string}>
     */
    public function filterSourceItems(array $items, Team $team): array
    {
        $positiveKeywords = $this->parseKeywords($team->positive_keywords);
        $negativeKeywords = $this->parseKeywords($team->negative_keywords);

        // No filtering needed if both lists are empty
        if (empty($positiveKeywords) && empty($negativeKeywords)) {
            return $items;
        }

        $filteredItems = [];

        foreach ($items as $item) {
            $title = $item['title'] ?? null;

            // If no title, fetch from HTML (for XML sitemaps)
            if (empty($title)) {
                $title = $this->fetchTitleFromUrl($item['uri']);
                $item['title'] = $title ?? '';
            }

            // Apply keyword filtering
            if ($this->shouldIncludePost($title ?? '', $team)) {
                $filteredItems[] = $item;
            }
        }

        return $filteredItems;
    }
}
