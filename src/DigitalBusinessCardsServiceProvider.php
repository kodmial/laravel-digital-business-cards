<?php

namespace DigitalCardKit\Laravel;

use DigitalCardKit\Laravel\Events\ContactExchangeCompleted;
use DigitalCardKit\Laravel\Listeners\QueueContactExchangeNotifications;
use DigitalCardKit\Laravel\Listeners\SendContactExchangeNotifications;
use DigitalCardKit\Laravel\Notifications\NotificationSender;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class DigitalBusinessCardsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/digital-business-cards.php', 'digital-business-cards');

        if (! $this->app->bound(NotificationSender::class)) {
            $this->app->bind(NotificationSender::class, function ($app): NotificationSender {
                $sender = $app['config']->get('digital-business-cards.notification_sender');

                return $app->make($sender);
            });
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'digital-business-cards');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'digital-business-cards');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        if (config('digital-business-cards.notifications.register_default_listener', true)) {
            Event::listen(
                ContactExchangeCompleted::class,
                config('digital-business-cards.notifications.queued', false)
                    ? QueueContactExchangeNotifications::class
                    : SendContactExchangeNotifications::class,
            );
        }

        $this->publishes([
            __DIR__.'/../config/digital-business-cards.php' => config_path('digital-business-cards.php'),
        ], 'digital-business-cards-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'digital-business-cards-migrations');

        $this->publishes([
            __DIR__.'/../resources/css/card.css' => public_path('vendor/digital-business-cards/card.css'),
            __DIR__.'/../resources/js/card.js' => public_path('vendor/digital-business-cards/card.js'),
        ], 'digital-business-cards-assets');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/digital-business-cards'),
        ], 'digital-business-cards-views');

        $this->publishes([
            __DIR__.'/../resources/lang' => lang_path('vendor/digital-business-cards'),
        ], 'digital-business-cards-translations');
    }
}
