<?php

namespace App\Providers;

use App\Events\ContentPieceGenerated;
use App\Events\ContentPieceGenerationFailed;
use App\Events\OpenAIRequestFailed;
use App\Events\PasswordChanged;
use App\Events\PasswordReset;
use App\Events\PostFound;
use App\Events\SourceCreated;
use App\Events\SourceDeleted;
use App\Events\SourceMonitoringFailed;
use App\Events\SourceUpdated;
use App\Events\TokenLimitExceeded;
use App\Events\TwoFactorDisabled;
use App\Events\TwoFactorEnabled;
use App\Events\UserLoggedIn;
use App\Events\WebhookDeliveryFailed;
use App\Listeners\LogActivityToDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register User observer for 2FA events
        \App\Models\User::observe(\App\Observers\UserObserver::class);

        // Register activity logging events
        Event::listen([
            // User events
            UserLoggedIn::class,
            TwoFactorEnabled::class,
            TwoFactorDisabled::class,
            PasswordChanged::class,
            PasswordReset::class,

            // Domain events
            PostFound::class,
            SourceCreated::class,
            SourceUpdated::class,
            SourceDeleted::class,

            // Error/Warning events
            SourceMonitoringFailed::class,
            ContentPieceGenerated::class,
            ContentPieceGenerationFailed::class,
            OpenAIRequestFailed::class,
            WebhookDeliveryFailed::class,
            TokenLimitExceeded::class,
        ], LogActivityToDatabase::class);
    }
}
