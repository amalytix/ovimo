<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class SourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sources = [
            [
                'team_id' => 1,
                'internal_name' => 'Movesell (Pages)',
                'type' => 'XML_SITEMAP',
                'url' => 'https://movesell.de/page-sitemap1.xml',
                'css_selector_title' => null,
                'css_selector_link' => null,
                'keywords' => null,
                'monitoring_interval' => 'DAILY',
                'is_active' => true,
                'should_notify' => false,
                'auto_summarize' => false,
            ],
            [
                'team_id' => 1,
                'internal_name' => 'Movesell (Posts)',
                'type' => 'XML_SITEMAP',
                'url' => 'https://movesell.de/post-sitemap1.xml',
                'css_selector_title' => null,
                'css_selector_link' => null,
                'keywords' => null,
                'monitoring_interval' => 'DAILY',
                'is_active' => true,
                'should_notify' => false,
                'auto_summarize' => false,
            ],
            [
                'team_id' => 1,
                'internal_name' => 'AMVisor (Pages)',
                'type' => 'XML_SITEMAP',
                'url' => 'https://www.amvisor.com/page-sitemap.xml',
                'css_selector_title' => null,
                'css_selector_link' => null,
                'keywords' => null,
                'monitoring_interval' => 'DAILY',
                'is_active' => true,
                'should_notify' => false,
                'auto_summarize' => false,
            ],
            [
                'team_id' => 1,
                'internal_name' => 'AMVisor (Posts)',
                'type' => 'XML_SITEMAP',
                'url' => 'https://www.amvisor.com/post-sitemap.xml',
                'css_selector_title' => null,
                'css_selector_link' => null,
                'keywords' => null,
                'monitoring_interval' => 'DAILY',
                'is_active' => true,
                'should_notify' => false,
                'auto_summarize' => false,
            ],
            [
                'team_id' => 1,
                'internal_name' => 'Amazon Science',
                'type' => 'RSS',
                'url' => 'https://www.amazon.science/index.rss',
                'css_selector_title' => null,
                'css_selector_link' => null,
                'keywords' => null,
                'monitoring_interval' => 'DAILY',
                'is_active' => true,
                'should_notify' => false,
                'auto_summarize' => false,
            ],
            [
                'team_id' => 1,
                'internal_name' => 'Hacker News RSS',
                'type' => 'WEBSITE',
                'url' => 'https://news.ycombinator.com/',
                'css_selector_title' => 'tr.athing.submission td.title span.titleline > a',
                'css_selector_link' => 'tr.athing.submission td.title span.titleline > a[href]',
                'keywords' => 'amazon',
                'monitoring_interval' => 'DAILY',
                'is_active' => true,
                'should_notify' => false,
                'auto_summarize' => false,
            ],
            [
                'team_id' => 1,
                'internal_name' => 'Amazon Press Retail',
                'type' => 'RSS',
                'url' => 'https://www.aboutamazon.com/news/retail.rss',
                'css_selector_title' => null,
                'css_selector_link' => null,
                'keywords' => null,
                'monitoring_interval' => 'DAILY',
                'is_active' => true,
                'should_notify' => false,
                'auto_summarize' => false,
            ],
            [
                'team_id' => 1,
                'internal_name' => 'Amazon Press Company',
                'type' => 'RSS',
                'url' => 'https://www.aboutamazon.com/news/company-news.rss',
                'css_selector_title' => null,
                'css_selector_link' => null,
                'keywords' => null,
                'monitoring_interval' => 'DAILY',
                'is_active' => true,
                'should_notify' => false,
                'auto_summarize' => false,
            ],
        ];

        foreach ($sources as $source) {
            Source::create($source);
        }
    }
}
