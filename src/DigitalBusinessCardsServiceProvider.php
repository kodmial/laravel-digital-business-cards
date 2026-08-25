<?php

namespace DigitalCardKit\Laravel;

use DigitalCardKit\Laravel\Livewire\ContactExchangeForm;
use DigitalCardKit\Laravel\Support\Config;
use DigitalCardKit\Laravel\Support\RateLimits;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DigitalBusinessCardsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('digital-business-cards')
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasRoute('web')
            ->hasMigrations([
                '2026_07_22_150000_create_digital_business_cards_tables',
                '2026_07_24_130000_reconcile_digital_business_cards_columns',
            ])
            ->runsMigrations();
    }

    public function boot(): void
    {
        parent::boot();

        RateLimits::register();
        Livewire::component('digital-business-cards.contact-exchange-form', ContactExchangeForm::class);

        $this->registerLeadExportGate();

        $this->publishes([
            __DIR__.'/../resources/css/card.css' => public_path('vendor/digital-business-cards/card.css'),
            __DIR__.'/../resources/js/card.js' => public_path('vendor/digital-business-cards/card.js'),
        ], 'digital-business-cards-assets');
    }

    private function registerLeadExportGate(): void
    {
        $ability = Config::leadExportAbility();

        if (Gate::has($ability)) {
            return;
        }

        Gate::define($ability, static fn (?Authenticatable $user = null): bool => $user !== null
            || self::authenticatedOnAPanelGuard());
    }

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
