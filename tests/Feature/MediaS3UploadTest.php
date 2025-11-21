<?php

use App\Models\Media;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

test('uploads flammschale image to real s3 bucket via presign', function () {
    if (! env('RUN_REAL_S3_TESTS')) {
        $this->markTestSkipped('RUN_REAL_S3_TESTS not enabled.');
    }

    if (! env('AWS_ACCESS_KEY_ID') || ! env('AWS_SECRET_ACCESS_KEY') || ! env('AWS_BUCKET')) {
        $this->markTestSkipped('AWS credentials not configured; skipping real S3 upload test.');
    }

    $fixturePath = base_path('tests/fixtures/flammschale.jpg');

    if (! file_exists($fixturePath)) {
        $this->markTestSkipped('Fixture flammschale.jpg missing.');
    }

    [$user, $team] = createUserWithTeam();
    $this->actingAs($user);

    $size = filesize($fixturePath);
    $mimeType = 'image/jpeg';

    $presign = $this->postJson('/media/presign', [
        'filename' => 'flammschale.jpg',
        'mime_type' => $mimeType,
        'file_size' => $size,
    ])->assertOk()->json();

    $request = Http::asMultipart();

    foreach ($presign['fields'] as $key => $value) {
        $request = $request->attach($key, $value);
    }

    try {
        $uploadResponse = $request
            ->attach('file', fopen($fixturePath, 'r'), 'flammschale.jpg')
            ->post($presign['url']);
    } catch (ConnectException|HttpConnectionException $exception) {
        $this->markTestSkipped('S3 endpoint unreachable from test environment: '.$exception->getMessage());
    }

    $uploadResponse->throw();

    $storeResponse = $this->postJson('/media', [
        's3_key' => $presign['s3_key'],
        'filename' => 'flammschale.jpg',
        'stored_filename' => $presign['stored_filename'],
        'file_path' => $presign['file_path'],
        'mime_type' => $mimeType,
        'file_size' => $size,
    ])->assertCreated();

    $mediaId = $storeResponse->json('media.id');

    expect($mediaId)->not->toBeNull();
    expect(Storage::disk('s3')->exists($presign['s3_key']))->toBeTrue();

    // Clean up the test artifact without failing the test if deletion fails.
    try {
        Storage::disk('s3')->delete($presign['s3_key']);
    } catch (\Throwable) {
        // Ignore cleanup failures in integration run
    }

    Media::whereKey($mediaId)->delete();
});
