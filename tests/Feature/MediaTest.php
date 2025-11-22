<?php

use App\Events\MediaUploaded;
use App\Models\Media;
use App\Models\MediaTag;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

test('media index is team scoped', function () {
    Storage::fake('s3');

    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();

    $teamMedia = Media::factory()->create([
        'team_id' => $team->id,
        'uploaded_by' => $user->id,
    ]);

    Media::factory()->create([
        'team_id' => $otherTeam->id,
        'uploaded_by' => $otherUser->id,
    ]);

    $response = $this->actingAs($user)->get('/media');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Media/Index')
        ->has('media.data', 1)
        ->where('media.data.0.id', $teamMedia->id)
    );
});

test('user can store media metadata after upload', function () {
    Storage::fake('s3');
    Event::fake([MediaUploaded::class]);

    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->postJson('/media', [
        's3_key' => "teams/{$team->id}/images/example.jpg",
        'filename' => 'example.jpg',
        'stored_filename' => 'example-stored.jpg',
        'file_path' => "teams/{$team->id}/images/example.jpg",
        'mime_type' => 'image/jpeg',
        'file_size' => 12345,
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('media', [
        'filename' => 'example.jpg',
        'team_id' => $team->id,
        'uploaded_by' => $user->id,
    ]);

    Event::assertDispatched(MediaUploaded::class);
});

test('media update can sync tags within team', function () {
    Storage::fake('s3');

    [$user, $team] = createUserWithTeam();

    $tag = MediaTag::factory()->create(['team_id' => $team->id]);
    $media = Media::factory()->create([
        'team_id' => $team->id,
        'uploaded_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->patchJson("/media/{$media->id}", [
        'filename' => 'renamed-file.jpg',
        'tag_ids' => [$tag->id],
    ]);

    $response->assertOk();

    expect($media->fresh()->filename)->toBe('renamed-file.jpg');
    expect($media->fresh()->tags()->pluck('media_tags.id')->all())->toEqual([$tag->id]);
});

test('media index defaults to uploaded date descending', function () {
    [$user, $team] = createUserWithTeam();

    $older = Media::factory()->create([
        'team_id' => $team->id,
        'uploaded_by' => $user->id,
        'created_at' => now()->subDay(),
    ]);

    $newer = Media::factory()->create([
        'team_id' => $team->id,
        'uploaded_by' => $user->id,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/media');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('media.data.0.id', $newer->id)
        ->where('media.data.1.id', $older->id)
    );
});

test('media index can be sorted by filename', function () {
    [$user, $team] = createUserWithTeam();

    $alpha = Media::factory()->create([
        'team_id' => $team->id,
        'uploaded_by' => $user->id,
        'filename' => 'alpha.pdf',
    ]);

    $zulu = Media::factory()->create([
        'team_id' => $team->id,
        'uploaded_by' => $user->id,
        'filename' => 'zulu.pdf',
    ]);

    $response = $this->actingAs($user)->get('/media?sort_by=filename&sort_dir=asc');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('media.data.0.id', $alpha->id)
        ->where('media.data.1.id', $zulu->id)
    );
});

test('media search matches filenames with umlauts', function () {
    [$user, $team] = createUserWithTeam();

    $media = Media::factory()->create([
        'team_id' => $team->id,
        'uploaded_by' => $user->id,
        'filename' => "Vo\u{0308}gel-5.jpg", // V + ö (decomposed)
    ]);

    // Searching with composed umlaut should still find it
    $response = $this->actingAs($user)->get('/media?search=Vögel');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('media.data.0.id', $media->id)
    );
});

test('media download endpoint redirects to signed url', function () {
    [$user, $team] = createUserWithTeam();

    $media = Media::factory()->create([
        'team_id' => $team->id,
        'uploaded_by' => $user->id,
        's3_key' => "teams/{$team->id}/images/example.jpg",
        'filename' => 'example.jpg',
    ]);

    Storage::partialMock()
        ->shouldReceive('disk')
        ->with('s3')
        ->andReturnSelf();

    Storage::shouldReceive('temporaryUrl')
        ->once()
        ->with($media->s3_key, \Mockery::type(\DateTimeInterface::class), \Mockery::subset([
            'ResponseContentDisposition' => 'attachment; filename="example.jpg"',
        ]))
        ->andReturn('https://signed-url.test/example.jpg');

    $response = $this->actingAs($user)->get("/media/{$media->id}/download");

    $response->assertRedirect('https://signed-url.test/example.jpg');
});
