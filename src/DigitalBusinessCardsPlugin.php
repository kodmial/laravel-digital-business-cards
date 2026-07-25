<?php

namespace DigitalCardKit\Laravel;

use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCardLeads\DigitalBusinessCardLeadResource;
use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\DigitalBusinessCardResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

class DigitalBusinessCardsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'digital-business-cards';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            DigitalBusinessCardResource::class,
            DigitalBusinessCardLeadResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
