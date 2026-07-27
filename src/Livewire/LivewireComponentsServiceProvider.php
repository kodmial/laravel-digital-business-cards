<?php

namespace DigitalCardKit\Laravel\Livewire;

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for Livewire components registration
 * 
 * This provider is loaded conditionally based on configuration
 * to ensure Livewire integration is optional.
 */
class LivewireComponentsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register any Livewire-specific services here
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load Livewire components when Livewire is available
        if (class_exists(\Livewire\Livewire::class)) {
            $this->app->register(LivewireServiceProvider::class);
        }
    }
}
