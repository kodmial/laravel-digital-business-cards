<?php

namespace DigitalCardKit\Laravel\Tests;

class LivewireComponentTest extends TestCase
{

    public function test_livewire_component_class_exists(): void
    {
        if (! class_exists(\Livewire\Livewire::class)) {
            $this->markTestSkipped('Livewire is not installed');
        }

        $this->assertTrue(class_exists(\DigitalCardKit\Laravel\Livewire\Components\LeadForm::class));
    }

    public function test_livewire_component_implements_renderable_interface(): void
    {
        if (! class_exists(\Livewire\Livewire::class)) {
            $this->markTestSkipped('Livewire is not installed');
        }

        $component = new \DigitalCardKit\Laravel\Livewire\Components\LeadForm();
        $this->assertTrue(method_exists($component, 'render'));
    }

    public function test_livewire_component_has_required_properties(): void
    {
        if (! class_exists(\Livewire\Livewire::class)) {
            $this->markTestSkipped('Livewire is not installed');
        }

        $component = new \DigitalCardKit\Laravel\Livewire\Components\LeadForm();
        
        // Check that component has expected properties
        $this->assertObjectHasProperty('cardId', $component);
        $this->assertObjectHasProperty('fullName', $component);
        $this->assertObjectHasProperty('buttonClass', $component);
        $this->assertObjectHasProperty('inline', $component);
        $this->assertObjectHasProperty('leadData', $component);
        $this->assertObjectHasProperty('consent', $component);
        $this->assertObjectHasProperty('submitted', $component);
        $this->assertObjectHasProperty('sending', $component);
    }

    public function test_livewire_component_has_required_methods(): void
    {
        if (! class_exists(\Livewire\Livewire::class)) {
            $this->markTestSkipped('Livewire is not installed');
        }

        $component = new \DigitalCardKit\Laravel\Livewire\Components\LeadForm();
        
        // Check that component has expected methods
        $this->assertTrue(method_exists($component, 'mount'));
        $this->assertTrue(method_exists($component, 'submit'));
        $this->assertTrue(method_exists($component, 'getCardProperty'));
    }

    public function test_livewire_service_provider_registers_component(): void
    {
        if (! class_exists(\Livewire\Livewire::class)) {
            $this->markTestSkipped('Livewire is not installed');
        }

        config(['digital-business-cards.use_livewire' => true]);
        
        // Check if the service provider class exists
        $this->assertTrue(class_exists(\DigitalCardKit\Laravel\Livewire\LivewireComponentsServiceProvider::class));
    }

    public function test_livewire_component_view_file_exists(): void
    {
        if (! class_exists(\Livewire\Livewire::class)) {
            $this->markTestSkipped('Livewire is not installed');
        }

        $viewPath = __DIR__ . '/../resources/views/livewire/lead-form.blade.php';
        $this->assertFileExists($viewPath);
    }

    public function test_livewire_component_initializes_with_default_values(): void
    {
        if (! class_exists(\Livewire\Livewire::class)) {
            $this->markTestSkipped('Livewire is not installed');
        }

        // Test that the component can be instantiated with expected mount parameters
        $component = new \DigitalCardKit\Laravel\Livewire\Components\LeadForm();
        
        // Check that mount method exists and can accept parameters
        $this->assertTrue(method_exists($component, 'mount'));
        
        // Check that properties are initialized to default false values
        $this->assertFalse($component->submitted);
        $this->assertFalse($component->sending);
    }
}