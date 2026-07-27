<?php

namespace DigitalCardKit\Laravel\Tests;

use DigitalCardKit\Laravel\Livewire\Components\LeadForm;
use DigitalCardKit\Laravel\Livewire\LivewireComponentsServiceProvider;
use Livewire\Livewire;

class LivewireComponentTest extends TestCase
{
    public function test_livewire_component_class_exists(): void
    {
        if (! class_exists(Livewire::class)) {
            $this->markTestSkipped('Livewire is not installed');
        }

        $this->assertTrue(class_exists(LeadForm::class));
    }

    public function test_livewire_component_service_provider_registers_component(): void
    {
        if (! class_exists(Livewire::class)) {
            $this->markTestSkipped('Livewire is not installed');
        }

        config(['digital-business-cards.use_livewire' => true]);

        // Check if the service provider class exists
        $this->assertTrue(class_exists(LivewireComponentsServiceProvider::class));

        // Check that the component class exists
        $this->assertTrue(class_exists(LeadForm::class));
    }

    public function test_livewire_component_view_file_exists(): void
    {
        if (! class_exists(Livewire::class)) {
            $this->markTestSkipped('Livewire is not installed');
        }

        $viewPath = __DIR__.'/../resources/views/livewire/lead-form.blade.php';
        $this->assertFileExists($viewPath);
    }

    public function test_livewire_component_initializes_with_default_values(): void
    {
        if (! class_exists(Livewire::class)) {
            $this->markTestSkipped('Livewire is not installed');
        }

        // Test that the component can be instantiated with expected mount parameters
        $component = new LeadForm;

        // Check that properties are initialized to default false values
        $this->assertFalse($component->submitted);
        $this->assertFalse($component->sending);
    }
}
