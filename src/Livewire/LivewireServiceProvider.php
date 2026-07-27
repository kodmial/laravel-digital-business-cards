<?php

namespace DigitalCardKit\Laravel\Livewire;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class LivewireServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! class_exists(Livewire::class)) {
            return;
        }

        $this->app->register(LivewireComponentsServiceProvider::class);
    }
}
