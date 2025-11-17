<?php

namespace Database\Seeders;

use App\Models\Post;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $posts = require __DIR__.'/data/posts.php';

        foreach ($posts as $postData) {
            Post::create($postData);
        }

        $this->command->info('Seeded '.count($posts).' posts.');
    }
}
