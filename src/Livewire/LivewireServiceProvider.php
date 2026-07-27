<?php

namespace DigitalCardKit\Laravel\Livewire;

use DigitalCardKit\Laravel\Livewire\Components\LeadForm;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Livewire service provider for digital business cards
 * 
 * Registers Livewire components for reactive form handling
 * when use_livewire is enabled in configuration.
 */
class LivewireServiceProvider extends ServiceProvider
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
        Livewire::component('lead-form', LeadForm::class);
    }
}
