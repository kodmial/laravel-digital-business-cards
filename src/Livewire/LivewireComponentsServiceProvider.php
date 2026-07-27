<?php

namespace DigitalCardKit\Laravel\Livewire;

use DigitalCardKit\Laravel\Livewire\Components\LeadForm;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class LivewireComponentsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! class_exists(Livewire::class)) {
            return;
        }

        Livewire::component('lead-form', LeadForm::class);
    }
}
