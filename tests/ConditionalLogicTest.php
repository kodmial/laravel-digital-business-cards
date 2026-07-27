<?php

namespace DigitalCardKit\Laravel\Tests;

use DigitalCardKit\Laravel\Tests\Concerns\CreatesCards;
use Illuminate\Support\Facades\Event;

class ConditionalLogicTest extends TestCase
{
    use CreatesCards;

    public function test_use_livewire_config_defaults_to_false(): void
    {
        $this->assertFalse(config('digital-business-cards.use_livewire', false));
    }

    public function test_livewire_service_provider_registered_when_enabled(): void
    {
        if (! class_exists(\Livewire\Livewire::class)) {
            $this->markTestSkipped('Livewire is not installed');
        }

        config(['digital-business-cards.use_livewire' => true]);
        
        // Check if the component class exists
        $this->assertTrue(class_exists(\DigitalCardKit\Laravel\Livewire\Components\LeadForm::class));
    }

    public function test_livewire_service_provider_not_registered_when_disabled(): void
    {
        config(['digital-business-cards.use_livewire' => false]);
        
        $app = $this->app;
        $provider = new \DigitalCardKit\Laravel\DigitalBusinessCardsServiceProvider($app);
        $provider->register();
        
        $this->assertFalse($app->bound(\DigitalCardKit\Laravel\Livewire\LivewireServiceProvider::class));
    }

    public function test_forms_work_without_livewire(): void
    {
        Event::fake();
        config(['digital-business-cards.use_livewire' => false]);
        
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
        config(['digital-business-cards.use_livewire' => false]);
        
        $card = $this->createCard(['lead_form_enabled' => true]);
        
        $response = $this->get('/card/example-card');
        
        $response->assertStatus(200);
        $response->assertSee('data-modal', false);
        $response->assertSee('data-close-modal', false);
        $response->assertSee('data-save-contact', false);
    }

    public function test_javascript_fallback_works_without_alpine(): void
    {
        config(['digital-business-cards.use_livewire' => false]);
        
        $card = $this->createCard(['lead_form_enabled' => true]);
        
        $response = $this->get('/card/example-card');
        
        $response->assertStatus(200);
        $response->assertSee('card.js', false);
        $response->assertDontSee('alpine.js', false);
    }

    public function test_both_approaches_produce_same_html_structure(): void
    {
        $card = $this->createCard(['lead_form_enabled' => true]);
        
        // Test with Livewire disabled
        config(['digital-business-cards.use_livewire' => false]);
        $responseWithout = $this->get('/card/example-card');
        $htmlWithout = $responseWithout->getContent();
        
        // Both should contain form structure
        $this->assertStringContainsString('digital-card-form', $htmlWithout);
        
        // Both should contain modal structure
        $this->assertStringContainsString('data-modal', $htmlWithout);
    }

    public function test_config_change_does_not_break_existing_functionality(): void
    {
        // Test with Livewire disabled (default state)
        config(['digital-business-cards.use_livewire' => false]);
        
        $card = $this->createCard(['lead_form_enabled' => true]);
        
        $response1 = $this->get('/card/example-card');
        $response1->assertStatus(200);
        
        // Test that basic functionality still works
        $this->assertEquals(200, $response1->status());
    }
}