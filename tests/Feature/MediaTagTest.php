<?php

use App\Models\Media;
use App\Models\MediaTag;
use Illuminate\Support\Facades\Storage;

test('user can create media tag for team', function () {
    Storage::fake('s3');

    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/media-tags', [
        'name' => 'Screenshots',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('media_tags', [
        'name' => 'Screenshots',
        'team_id' => $team->id,
    ]);
});

test('duplicate tag name for same team is rejected', function () {
    Storage::fake('s3');

    [$user, $team] = createUserWithTeam();
    MediaTag::factory()->create(['team_id' => $team->id, 'name' => 'Logos', 'slug' => 'logos']);

    $response = $this->actingAs($user)->post('/media-tags', [
        'name' => 'Logos',
    ]);

    $response->assertSessionHasErrors(['name']);
});

test('deleting tag removes relations', function () {
    Storage::fake('s3');

    [$user, $team] = createUserWithTeam();
    $tag = MediaTag::factory()->create(['team_id' => $team->id]);
    $media = Media::factory()->create(['team_id' => $team->id, 'uploaded_by' => $user->id]);
    $media->tags()->attach($tag);

    $response = $this->actingAs($user)->delete("/media-tags/{$tag->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('media_tags', ['id' => $tag->id]);
    $this->assertDatabaseMissing('media_media_tag', ['media_tag_id' => $tag->id]);
});
