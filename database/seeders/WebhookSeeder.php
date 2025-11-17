<?php

namespace Database\Seeders;

use App\Models\Webhook;
use Illuminate\Database\Seeder;

class WebhookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Webhook::create([
            'team_id' => 1,
            'name' => 'n8n Webhook',
            'url' => 'https://n8n.amalytix.net/webhook/66ca169e-3b6d-4401-8ee6-955d3422c83c',
            'event' => 'NEW_POSTS',
            'is_active' => true,
            'secret' => null,
            'failure_count' => 0,
        ]);
    }
}
