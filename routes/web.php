<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\BackgroundSourceController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\ContentDerivativeController;
use App\Http\Controllers\ContentPieceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DerivativeActivitiesController;
use App\Http\Controllers\DerivativeActivityController;
use App\Http\Controllers\ImageGenerationController;
use App\Http\Controllers\Integrations\LinkedInController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MediaTagController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SourceController;
use App\Http\Controllers\TeamInvitationController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\TeamSwitchController;
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

// Team invitation acceptance (no auth required - will redirect to login if needed)
Route::get('invitations/{token}/accept', [TeamInvitationController::class, 'accept'])
    ->name('team-invitations.accept');

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
    Route::get('content-pieces/calendar', [ContentPieceController::class, 'calendar'])->name('content-pieces.calendar');
    Route::post('content-pieces/bulk-delete', [ContentPieceController::class, 'bulkDelete'])->name('content-pieces.bulk-delete');
    Route::post('content-pieces/bulk-unset-publish-date', [ContentPieceController::class, 'bulkUnsetPublishDate'])->name('content-pieces.bulk-unset-publish-date');

    // Content derivative routes
    Route::prefix('content-pieces/{contentPiece}')->name('content-pieces.')->group(function () {
        Route::post('derivatives', [ContentDerivativeController::class, 'store'])->name('derivatives.store');
        Route::put('derivatives/{derivative}', [ContentDerivativeController::class, 'update'])->name('derivatives.update');
        Route::delete('derivatives/{derivative}', [ContentDerivativeController::class, 'destroy'])->name('derivatives.destroy');
        Route::post('derivatives/{derivative}/generate', [ContentDerivativeController::class, 'generate'])
            ->middleware('token.limit')
            ->name('derivatives.generate');
        Route::get('derivatives/{derivative}/status', [ContentDerivativeController::class, 'status'])->name('derivatives.status');
        Route::get('derivatives/{derivative}/activities', [DerivativeActivityController::class, 'index'])->name('derivatives.activities.index');
        Route::post('derivatives/{derivative}/activities', [DerivativeActivityController::class, 'store'])->name('derivatives.activities.store');

        Route::post('sources', [BackgroundSourceController::class, 'store'])->name('sources.store');
        Route::put('sources/{source}', [BackgroundSourceController::class, 'update'])->name('sources.update');
        Route::delete('sources/{source}', [BackgroundSourceController::class, 'destroy'])->name('sources.destroy');
        Route::post('sources/reorder', [BackgroundSourceController::class, 'reorder'])->name('sources.reorder');
    });

    // Channel management routes
    Route::get('channels', [ChannelController::class, 'index'])->name('channels.index');
    Route::post('channels', [ChannelController::class, 'store'])->name('channels.store');
    Route::put('channels/{channel}', [ChannelController::class, 'update'])->name('channels.update');
    Route::delete('channels/{channel}', [ChannelController::class, 'destroy'])->name('channels.destroy');
    Route::post('channels/reorder', [ChannelController::class, 'reorder'])->name('channels.reorder');

    // Image generation routes for content pieces
    Route::post('content-pieces/{content_piece}/image-generations', [ImageGenerationController::class, 'store'])
        ->middleware('token.limit')
        ->name('content-pieces.image-generations.store');
    Route::patch('content-pieces/{content_piece}/image-generations/{image_generation}', [ImageGenerationController::class, 'update'])
        ->name('content-pieces.image-generations.update');
    Route::post('content-pieces/{content_piece}/image-generations/{image_generation}/generate', [ImageGenerationController::class, 'generate'])
        ->middleware('token.limit')
        ->name('content-pieces.image-generations.generate');
    Route::get('content-pieces/{content_piece}/image-generations/{image_generation}/status', [ImageGenerationController::class, 'status'])
        ->name('content-pieces.image-generations.status');
    Route::delete('content-pieces/{content_piece}/image-generations/{image_generation}', [ImageGenerationController::class, 'destroy'])
        ->name('content-pieces.image-generations.destroy');

    Route::resource('webhooks', WebhookController::class)->except(['show']);
    Route::post('webhooks/{webhook}/test', [WebhookController::class, 'test'])->name('webhooks.test');

    Route::get('usage', [UsageController::class, 'index'])->name('usage.index');

    Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('derivative-activities', [DerivativeActivitiesController::class, 'index'])->name('derivative-activities.index');

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

    Route::prefix('integrations/linkedin')->name('integrations.linkedin.')->group(function () {
        Route::get('/', [LinkedInController::class, 'index'])->name('index');
        Route::get('/connect', [LinkedInController::class, 'redirect'])->name('connect');
        Route::get('/callback', [LinkedInController::class, 'callback'])->name('callback');
        Route::get('/callback-member', [LinkedInController::class, 'callback'])->name('callback-member');
        Route::delete('/{integration}', [LinkedInController::class, 'disconnect'])->name('disconnect');
    });

    // Team management
    Route::post('team-invitations', [TeamInvitationController::class, 'store'])->name('team-invitations.store');
    Route::delete('team-invitations/{invitation}', [TeamInvitationController::class, 'destroy'])->name('team-invitations.destroy');
    Route::delete('team-members/{user}', [TeamMemberController::class, 'destroy'])->name('team-members.destroy');
    Route::post('team-members/leave', [TeamMemberController::class, 'leave'])->name('team-members.leave');
    Route::post('teams/{team}/switch', TeamSwitchController::class)->name('teams.switch');
});

require __DIR__.'/settings.php';
