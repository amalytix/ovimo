<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SourceParser
{
    /**
     * Parse a source URL and return found items.
     *
     * @return array<int, array{uri: string, title?: string}>
     */
    public function parse(string $url, string $type): array
    {
        $response = Http::timeout(30)->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to fetch URL: {$url}");
        }

        $content = $response->body();

        return match ($type) {
            'RSS' => $this->parseRss($content),
            'XML_SITEMAP' => $this->parseXmlSitemap($content),
            default => throw new \InvalidArgumentException("Unknown source type: {$type}"),
        };
    }

    /**
     * @return array<int, array{uri: string, title?: string}>
     */
    private function parseRss(string $content): array
    {
        $xml = @simplexml_load_string($content);

        if ($xml === false) {
            throw new \RuntimeException('Failed to parse RSS feed');
        }

        $items = [];

        // Handle both RSS 2.0 and Atom feeds
        if (isset($xml->channel->item)) {
            // RSS 2.0
            foreach ($xml->channel->item as $item) {
                $link = (string) $item->link;
                if (! empty($link)) {
                    $items[] = [
                        'uri' => $link,
                        'title' => (string) ($item->title ?? ''),
                    ];
                }
            }
        } elseif (isset($xml->entry)) {
            // Atom feed
            foreach ($xml->entry as $entry) {
                $link = '';
                if (isset($entry->link)) {
                    $link = (string) $entry->link['href'];
                }
                if (! empty($link)) {
                    $items[] = [
                        'uri' => $link,
                        'title' => (string) ($entry->title ?? ''),
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * @return array<int, array{uri: string}>
     */
    private function parseXmlSitemap(string $content): array
    {
        $xml = @simplexml_load_string($content);

        if ($xml === false) {
            throw new \RuntimeException('Failed to parse XML sitemap');
        }

        $items = [];

        // Register namespace if present
        $namespaces = $xml->getNamespaces(true);
        if (isset($namespaces[''])) {
            $xml->registerXPathNamespace('sm', $namespaces['']);
            $urls = $xml->xpath('//sm:url/sm:loc');
        } else {
            $urls = $xml->xpath('//url/loc') ?: $xml->xpath('//loc');
        }

        if ($urls) {
            foreach ($urls as $url) {
                $uri = (string) $url;
                if (! empty($uri)) {
                    $items[] = ['uri' => $uri];
                }
            }
        }

        return $items;
    }
}
