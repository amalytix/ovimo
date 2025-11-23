<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ContentPieceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MediaTagController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SourceController;
use App\Http\Controllers\UsageController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified', 'team.valid'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('sources', SourceController::class);
    Route::post('sources/{source}/check', [SourceController::class, 'check'])->name('sources.check');
    Route::post('sources/analyze-webpage', [SourceController::class, 'analyzeWebpage'])
        ->middleware('token.limit')
        ->name('sources.analyze-webpage');
    Route::post('sources/test-extraction', [SourceController::class, 'testExtraction'])->name('sources.test-extraction');
    Route::resource('prompts', PromptController::class)->except(['show']);
    Route::post('prompts/{prompt}/set-default', [PromptController::class, 'setDefault'])->name('prompts.set-default');
    Route::resource('content-pieces', ContentPieceController::class)->except(['show']);
    Route::post('content-pieces/{content_piece}/generate', [ContentPieceController::class, 'generate'])
        ->middleware('token.limit')
        ->name('content-pieces.generate');
    Route::get('content-pieces/calendar', [ContentPieceController::class, 'calendar'])->name('content-pieces.calendar');
    Route::get('content-pieces/{content_piece}/status', [ContentPieceController::class, 'status'])->name('content-pieces.status');
    Route::patch('content-pieces/{content_piece}/status', [ContentPieceController::class, 'updateStatus'])->name('content-pieces.update-status');
    Route::post('content-pieces/bulk-delete', [ContentPieceController::class, 'bulkDelete'])->name('content-pieces.bulk-delete');
    Route::post('content-pieces/bulk-unset-publish-date', [ContentPieceController::class, 'bulkUnsetPublishDate'])->name('content-pieces.bulk-unset-publish-date');
    Route::post('content-pieces/bulk-update-status', [ContentPieceController::class, 'bulkUpdateStatus'])->name('content-pieces.bulk-update-status');

    Route::resource('webhooks', WebhookController::class)->except(['show']);
    Route::post('webhooks/{webhook}/test', [WebhookController::class, 'test'])->name('webhooks.test');

    Route::get('usage', [UsageController::class, 'index'])->name('usage.index');

    Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

    Route::get('team-settings', [SettingsController::class, 'index'])->name('team-settings.index');
    Route::put('team-settings', [SettingsController::class, 'update'])->name('team-settings.update');
    Route::post('team-settings/export-sources', [SettingsController::class, 'exportSources'])->name('team-settings.export-sources');
    Route::post('team-settings/import-sources', [SettingsController::class, 'importSources'])->name('team-settings.import-sources');

    Route::get('posts', [PostController::class, 'index'])->name('posts.index');
    Route::patch('posts/{post}/toggle-hidden', [PostController::class, 'toggleHidden'])->name('posts.toggle-hidden');
    Route::patch('posts/{post}/status', [PostController::class, 'updateStatus'])->name('posts.update-status');
    Route::post('posts/bulk-hide', [PostController::class, 'bulkHide'])->name('posts.bulk-hide');
    Route::post('posts/bulk-delete', [PostController::class, 'bulkDelete'])->name('posts.bulk-delete');
    Route::post('posts/hide-not-relevant', [PostController::class, 'hideNotRelevant'])->name('posts.hide-not-relevant');

    Route::get('media', [MediaController::class, 'index'])->name('media.index');
    Route::post('media/presign', [MediaController::class, 'presign'])->name('media.presign');
    Route::post('media', [MediaController::class, 'store'])->name('media.store');
    Route::get('media/{media}/download', [MediaController::class, 'download'])->name('media.download');
    Route::get('media/{media}/temporary', [MediaController::class, 'temporary'])->name('media.temporary');
    Route::get('media/{media}/view', [MediaController::class, 'view'])->name('media.view');
    Route::get('media/{media}', [MediaController::class, 'show'])->name('media.show');
    Route::patch('media/{media}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
    Route::post('media/bulk-delete', [MediaController::class, 'bulkDestroy'])->name('media.bulk-delete');
    Route::post('media/bulk-tag', [MediaController::class, 'bulkTag'])->name('media.bulk-tag');

    Route::get('media-tags', [MediaTagController::class, 'index'])->name('media-tags.index');
    Route::post('media-tags', [MediaTagController::class, 'store'])->name('media-tags.store');
    Route::patch('media-tags/{mediaTag}', [MediaTagController::class, 'update'])->name('media-tags.update');
    Route::delete('media-tags/{mediaTag}', [MediaTagController::class, 'destroy'])->name('media-tags.destroy');
});

require __DIR__.'/settings.php';
