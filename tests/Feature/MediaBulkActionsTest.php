<?php

use App\Events\MediaBulkDeleted;
use App\Models\Media;
use App\Models\MediaTag;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

test('bulk delete removes selected media for current team', function () {
    Storage::fake('s3');
    Event::fake([MediaBulkDeleted::class]);

    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();

    $mediaOne = Media::factory()->create(['team_id' => $team->id, 'uploaded_by' => $user->id]);
    $mediaTwo = Media::factory()->create(['team_id' => $team->id, 'uploaded_by' => $user->id]);
    $otherMedia = Media::factory()->create(['team_id' => $otherTeam->id, 'uploaded_by' => $otherUser->id]);

    $response = $this->actingAs($user)->postJson('/media/bulk-delete', [
        'media_ids' => [$mediaOne->id, $mediaTwo->id],
    ]);

    $response->assertOk();

    $this->assertDatabaseMissing('media', ['id' => $mediaOne->id]);
    $this->assertDatabaseMissing('media', ['id' => $mediaTwo->id]);
    $this->assertDatabaseHas('media', ['id' => $otherMedia->id]);

    Event::assertDispatched(MediaBulkDeleted::class);
});

test('bulk tag add attaches tags to all selected media', function () {
    Storage::fake('s3');

    [$user, $team] = createUserWithTeam();
    $tag = MediaTag::factory()->create(['team_id' => $team->id]);
    $media = Media::factory()->count(2)->create([
        'team_id' => $team->id,
        'uploaded_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->postJson('/media/bulk-tag', [
        'media_ids' => $media->pluck('id')->all(),
        'tag_ids' => [$tag->id],
        'action' => 'add_tags',
    ]);

    $response->assertOk();

    foreach ($media as $item) {
        expect($item->fresh()->tags()->pluck('media_tags.id')->all())->toEqual([$tag->id]);
    }
});
