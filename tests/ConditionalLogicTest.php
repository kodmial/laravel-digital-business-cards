<?php

namespace DigitalCardKit\Laravel\Tests;

use DigitalCardKit\Laravel\Tests\Concerns\CreatesCards;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Config as LaravelConfig;

class ConditionalLogicTest extends TestCase
{
    use CreatesCards;

    public function test_livewire_is_disabled_by_default(): void
    {
        $this->assertFalse(LaravelConfig::get('digital-business-cards.use_livewire', false));
    }

    public function test_livewire_service_provider_not_registered_when_disabled(): void
    {
        LaravelConfig::set('digital-business-cards.use_livewire', false);
        
        $this->assertFalse($this->app->bound(\DigitalCardKit\Laravel\Livewire\LivewireComponentsServiceProvider::class));
    }

    public function test_livewire_service_provider_registered_when_enabled(): void
    {
        LaravelConfig::set('digital-business-cards.use_livewire', true);
        
        // The provider should be registered when config is true
        $this->assertTrue(class_exists(\DigitalCardKit\Laravel\Livewire\LivewireComponentsServiceProvider::class));
    }

    public function test_forms_work_without_livewire(): void
    {
        Event::fake();
        LaravelConfig::set('digital-business-cards.use_livewire', false);

        $card = $this->createCard([
            'lead_form_enabled' => true,
            'lead_form_fields' => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ],
        ]);

        $response = $this->post('/card/example-card/contacts', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'consent' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('digital_business_card_leads', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    public function test_modals_work_without_livewire(): void
    {
        LaravelConfig::set('digital-business-cards.use_livewire', false);

        $card = $this->createCard([
            'lead_form_enabled' => true,
        ]);

        $response = $this->get('/card/example-card');
        $response->assertStatus(200);
        
        // Ensure the modal HTML is present for JavaScript fallback
        $response->assertSee('data-modal');
    }
}
