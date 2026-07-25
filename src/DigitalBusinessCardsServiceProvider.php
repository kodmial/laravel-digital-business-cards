<?php

namespace DigitalCardKit\Laravel;

use DigitalCardKit\Laravel\Events\ContactExchangeCompleted;
use DigitalCardKit\Laravel\Listeners\QueueContactExchangeNotifications;
use DigitalCardKit\Laravel\Listeners\SendContactExchangeNotifications;
use DigitalCardKit\Laravel\Notifications\NotificationSender;
use DigitalCardKit\Laravel\Support\Config;
use DigitalCardKit\Laravel\Support\RateLimits;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
        RateLimits::register();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'digital-business-cards');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'digital-business-cards');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->registerLeadExportGate();

        if (Config::get('notifications.register_default_listener', true)) {
            Event::listen(
                ContactExchangeCompleted::class,
                Config::get('notifications.queued', false)
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

    /**
     * The lead export dumps every stored contact, so it is gated even though
     * the route also carries host-configurable middleware. The default only
     * requires an authenticated user; a host that needs finer rules defines
     * the ability itself and this registration steps aside.
     */
    private function registerLeadExportGate(): void
    {
        $ability = Config::leadExportAbility();

        if (Gate::has($ability)) {
            return;
        }

        Gate::define($ability, static fn (?Authenticatable $user = null): bool => $user !== null
            || self::authenticatedOnAPanelGuard());
    }

    /**
     * Gate resolves its user from the default guard, but a Filament panel may
     * authenticate on its own (`->authGuard('admin')`). Without this the
     * packaged default would refuse an admin the route middleware just let in.
     */
    private static function authenticatedOnAPanelGuard(): bool
    {
        if (! class_exists(Filament::class)) {
            return false;
        }

        foreach (Filament::getPanels() as $panel) {
            if (Auth::guard($panel->getAuthGuard())->check()) {
                return true;
            }
        }

        return false;
    }
}
