<?php

namespace App\Services;

use App\Models\Source;
use Illuminate\Support\Facades\Http;
use XMLReader;

class SourceParser
{
    private WebsiteParserService $websiteParser;

    public function __construct(?WebsiteParserService $websiteParser = null)
    {
        $this->websiteParser = $websiteParser ?? new WebsiteParserService;
    }

    /**
     * Parse a source URL and return found items.
     *
     * @return array<int, array{uri: string, title?: string}>
     */
    public function parse(string $url, string $type, ?int $maxEntries = null, ?Source $source = null): array
    {
        return match ($type) {
            'RSS' => $this->parseRssStreaming($url, $maxEntries ?? config('services.rss.max_entries', 10)),
            'XML_SITEMAP' => $this->parseXmlSitemap($url, $maxEntries ?? config('services.xml.max_entries', 500)),
            'WEBSITE' => $this->parseWebsite($source, $maxEntries ?? config('services.website.max_entries', 100)),
            default => throw new \InvalidArgumentException("Unknown source type: {$type}"),
        };
    }

    /**
     * Parse a website using CSS selectors.
     *
     * @return array<int, array{uri: string, title: string}>
     */
    private function parseWebsite(?Source $source, int $maxEntries): array
    {
        if (! $source) {
            throw new \InvalidArgumentException('Source model is required for WEBSITE type');
        }

        if (! $source->css_selector_title || ! $source->css_selector_link) {
            throw new \InvalidArgumentException('CSS selectors are required for WEBSITE type');
        }

        $posts = $this->websiteParser->parse(
            $source->url,
            $source->css_selector_title,
            $source->css_selector_link,
            $source->keywords,
            $maxEntries
        );

        // Transform to match expected format
        return array_map(fn ($post) => [
            'uri' => $post['link'],
            'title' => $post['title'],
        ], $posts);
    }

    /**
     * Parse RSS/Atom feed using streaming XMLReader for memory efficiency.
     *
     * @return array<int, array{uri: string, title?: string}>
     */
    private function parseRssStreaming(string $url, int $maxEntries): array
    {
        $tempFile = $this->downloadToTempFile($url);

        try {
            $reader = new XMLReader;

            if (! $reader->open($tempFile)) {
                throw new \RuntimeException('Failed to open RSS feed for streaming');
            }

            $feedType = $this->detectFeedType($reader);
            $reader->close();

            // Reopen the file to parse from the beginning
            if (! $reader->open($tempFile)) {
                throw new \RuntimeException('Failed to reopen RSS feed for parsing');
            }

            if ($feedType === 'rss') {
                $items = $this->parseRssItems($reader, $maxEntries);
            } elseif ($feedType === 'atom') {
                $items = $this->parseAtomEntries($reader, $maxEntries);
            } else {
                $items = [];
            }

            $reader->close();

            return $items;
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Download content to a temporary file for streaming.
     */
    private function downloadToTempFile(string $url): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'rss_');

        $response = Http::timeout(30)->sink($tempFile)->get($url);

        if (! $response->successful()) {
            @unlink($tempFile);
            throw new \RuntimeException("Failed to fetch URL: {$url}");
        }

        return $tempFile;
    }

    /**
     * Detect if feed is RSS or Atom format.
     */
    private function detectFeedType(XMLReader $reader): string
    {
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT) {
                if ($reader->name === 'rss' || $reader->name === 'channel') {
                    return 'rss';
                }
                if ($reader->name === 'feed') {
                    return 'atom';
                }
            }
        }

        throw new \RuntimeException('Unable to detect feed type');
    }

    /**
     * Parse RSS 2.0 items using streaming reader.
     *
     * @return array<int, array{uri: string, title: string}>
     */
    private function parseRssItems(XMLReader $reader, int $maxEntries): array
    {
        $items = [];
        $count = 0;

        while ($count < $maxEntries && $reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'item') {
                $itemXml = $reader->readOuterXml();
                $item = @simplexml_load_string($itemXml);

                if ($item !== false) {
                    $link = (string) $item->link;
                    if (! empty($link)) {
                        $items[] = [
                            'uri' => $link,
                            'title' => (string) ($item->title ?? ''),
                        ];
                        $count++;
                    }
                }
            }
        }

        return $items;
    }

    /**
     * Parse Atom entries using streaming reader.
     *
     * @return array<int, array{uri: string, title: string}>
     */
    private function parseAtomEntries(XMLReader $reader, int $maxEntries): array
    {
        $items = [];
        $count = 0;

        while ($count < $maxEntries && $reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'entry') {
                $entryXml = $reader->readOuterXml();
                $entry = @simplexml_load_string($entryXml);

                if ($entry !== false) {
                    $link = '';
                    if (isset($entry->link)) {
                        $link = (string) $entry->link['href'];
                    }
                    if (! empty($link)) {
                        $items[] = [
                            'uri' => $link,
                            'title' => (string) ($entry->title ?? ''),
                        ];
                        $count++;
                    }
                }
            }
        }

        return $items;
    }

    /**
     * Parse XML sitemap with streaming support.
     *
     * @return array<int, array{uri: string}>
     */
    private function parseXmlSitemap(string $url, int $maxEntries): array
    {
        $tempFile = $this->downloadToTempFile($url);

        try {
            $items = [];
            $reader = new XMLReader;

            if (! $reader->open($tempFile)) {
                throw new \RuntimeException('Failed to open XML sitemap for streaming');
            }

            $count = 0;

            while ($reader->read() && $count < $maxEntries) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'loc') {
                    $reader->read(); // Move to text content
                    $uri = trim($reader->value);
                    if (! empty($uri)) {
                        $items[] = ['uri' => $uri];
                        $count++;
                    }
                }
            }

            $reader->close();

            return $items;
        } finally {
            @unlink($tempFile);
        }
    }
}
