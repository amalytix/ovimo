<?php

use App\Models\Post;
use App\Models\Source;

test('guests cannot access posts', function () {
    $this->get('/posts')->assertRedirect('/login');
});

test('authenticated users can view posts index', function () {
    [$user, $team] = createUserWithTeam();

    $this->actingAs($user)
        ->get('/posts')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Posts/Index'));
});

test('posts index only shows team posts', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();

    $teamSource = Source::factory()->create(['team_id' => $team->id]);
    $otherSource = Source::factory()->create(['team_id' => $otherTeam->id]);

    $teamPost = Post::factory()->create(['source_id' => $teamSource->id]);
    $otherPost = Post::factory()->create(['source_id' => $otherSource->id]);

    $response = $this->actingAs($user)->get('/posts');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Posts/Index')
        ->has('posts.data', 1)
        ->where('posts.data.0.id', $teamPost->id)
    );
});

test('authenticated users can toggle post hidden status', function () {
    [$user, $team] = createUserWithTeam();
    $source = Source::factory()->create(['team_id' => $team->id]);
    $post = Post::factory()->create(['source_id' => $source->id, 'is_hidden' => false]);

    $this->actingAs($user)
        ->patch("/posts/{$post->id}/toggle-hidden")
        ->assertRedirect();

    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'is_hidden' => true,
    ]);
});

test('users cannot toggle hidden status for other teams posts', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();

    $otherSource = Source::factory()->create(['team_id' => $otherTeam->id]);
    $otherPost = Post::factory()->create(['source_id' => $otherSource->id, 'is_hidden' => false]);

    $this->actingAs($user)
        ->patch("/posts/{$otherPost->id}/toggle-hidden")
        ->assertForbidden();

    $this->assertDatabaseHas('posts', [
        'id' => $otherPost->id,
        'is_hidden' => false,
    ]);
});

test('authenticated users can update post status', function () {
    [$user, $team] = createUserWithTeam();
    $source = Source::factory()->create(['team_id' => $team->id]);
    $post = Post::factory()->create(['source_id' => $source->id, 'status' => 'NOT_RELEVANT']);

    $this->actingAs($user)
        ->patch("/posts/{$post->id}/status", ['status' => 'CREATE_CONTENT'])
        ->assertRedirect();

    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'status' => 'CREATE_CONTENT',
    ]);
});

test('post status update validates status value', function () {
    [$user, $team] = createUserWithTeam();
    $source = Source::factory()->create(['team_id' => $team->id]);
    $post = Post::factory()->create(['source_id' => $source->id]);

    $this->actingAs($user)
        ->patch("/posts/{$post->id}/status", ['status' => 'INVALID'])
        ->assertSessionHasErrors(['status']);
});

test('users cannot update status for other teams posts', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();

    $otherSource = Source::factory()->create(['team_id' => $otherTeam->id]);
    $otherPost = Post::factory()->create(['source_id' => $otherSource->id, 'status' => 'NOT_RELEVANT']);

    $this->actingAs($user)
        ->patch("/posts/{$otherPost->id}/status", ['status' => 'CREATE_CONTENT'])
        ->assertForbidden();

    $this->assertDatabaseHas('posts', [
        'id' => $otherPost->id,
        'status' => 'NOT_RELEVANT',
    ]);
});

test('authenticated users can bulk hide posts', function () {
    [$user, $team] = createUserWithTeam();
    $source = Source::factory()->create(['team_id' => $team->id]);
    $posts = Post::factory()->count(3)->create(['source_id' => $source->id, 'is_hidden' => false]);

    $this->actingAs($user)
        ->post('/posts/bulk-hide', [
            'post_ids' => $posts->pluck('id')->toArray(),
        ])
        ->assertRedirect();

    foreach ($posts as $post) {
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'is_hidden' => true,
        ]);
    }
});

test('bulk hide only affects team posts', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();

    $teamSource = Source::factory()->create(['team_id' => $team->id]);
    $otherSource = Source::factory()->create(['team_id' => $otherTeam->id]);

    $teamPost = Post::factory()->create(['source_id' => $teamSource->id, 'is_hidden' => false]);
    $otherPost = Post::factory()->create(['source_id' => $otherSource->id, 'is_hidden' => false]);

    $this->actingAs($user)
        ->post('/posts/bulk-hide', [
            'post_ids' => [$teamPost->id, $otherPost->id],
        ])
        ->assertRedirect();

    // Team post should be updated
    $this->assertDatabaseHas('posts', [
        'id' => $teamPost->id,
        'is_hidden' => true,
    ]);

    // Other team's post should NOT be updated
    $this->assertDatabaseHas('posts', [
        'id' => $otherPost->id,
        'is_hidden' => false,
    ]);
});

test('posts index filters by source', function () {
    [$user, $team] = createUserWithTeam();
    $source1 = Source::factory()->create(['team_id' => $team->id]);
    $source2 = Source::factory()->create(['team_id' => $team->id]);

    $post1 = Post::factory()->create(['source_id' => $source1->id]);
    $post2 = Post::factory()->create(['source_id' => $source2->id]);

    $response = $this->actingAs($user)->get("/posts?source_id={$source1->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('posts.data', 1)
        ->where('posts.data.0.id', $post1->id)
    );
});

test('posts index hides hidden posts by default', function () {
    [$user, $team] = createUserWithTeam();
    $source = Source::factory()->create(['team_id' => $team->id]);

    $visiblePost = Post::factory()->create(['source_id' => $source->id, 'is_hidden' => false]);
    $hiddenPost = Post::factory()->create(['source_id' => $source->id, 'is_hidden' => true]);

    $response = $this->actingAs($user)->get('/posts');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('posts.data', 1)
        ->where('posts.data.0.id', $visiblePost->id)
    );
});

test('authenticated users can bulk delete posts', function () {
    [$user, $team] = createUserWithTeam();
    $source = Source::factory()->create(['team_id' => $team->id]);
    $posts = Post::factory()->count(3)->create(['source_id' => $source->id]);

    $this->actingAs($user)
        ->post('/posts/bulk-delete', [
            'post_ids' => $posts->pluck('id')->toArray(),
        ])
        ->assertRedirect();

    foreach ($posts as $post) {
        $this->assertDatabaseMissing('posts', [
            'id' => $post->id,
        ]);
    }
});

test('bulk delete only affects team posts', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();

    $teamSource = Source::factory()->create(['team_id' => $team->id]);
    $otherSource = Source::factory()->create(['team_id' => $otherTeam->id]);

    $teamPost = Post::factory()->create(['source_id' => $teamSource->id]);
    $otherPost = Post::factory()->create(['source_id' => $otherSource->id]);

    $this->actingAs($user)
        ->post('/posts/bulk-delete', [
            'post_ids' => [$teamPost->id, $otherPost->id],
        ])
        ->assertRedirect();

    // Team post should be deleted
    $this->assertDatabaseMissing('posts', [
        'id' => $teamPost->id,
    ]);

    // Other team's post should NOT be deleted
    $this->assertDatabaseHas('posts', [
        'id' => $otherPost->id,
    ]);
});
