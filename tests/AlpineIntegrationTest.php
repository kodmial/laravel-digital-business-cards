<?php

namespace DigitalCardKit\Laravel\Tests;

use DigitalCardKit\Laravel\Tests\Concerns\CreatesCards;

class AlpineIntegrationTest extends TestCase
{
    use CreatesCards;

    public function test_alpine_scripts_not_loaded_when_livewire_disabled(): void
    {
        config(['digital-business-cards.use_livewire' => false]);
        
        $card = $this->createCard(['lead_form_enabled' => true]);
        
        $response = $this->get('/card/example-card');
        
        $response->assertStatus(200);
        $response->assertDontSee('alpinejs@3.14.0', false);
        $response->assertDontSee('alpine.js', false);
    }

    public function test_alpine_data_attributes_not_present_when_disabled(): void
    {
        config(['digital-business-cards.use_livewire' => false]);
        
        $card = $this->createCard(['lead_form_enabled' => true]);
        
        $response = $this->get('/card/example-card');
        
        $response->assertStatus(200);
        $response->assertDontSee('x-data', false);
        $response->assertDontSee('x-init', false);
        $response->assertDontSee('x-show', false);
    }

    public function test_data_attributes_present_when_disabled(): void
    {
        config(['digital-business-cards.use_livewire' => false]);
        
        $card = $this->createCard(['lead_form_enabled' => true]);
        
        $response = $this->get('/card/example-card');
        
        $response->assertStatus(200);
        $response->assertSee('data-save-contact', false);
        $response->assertSee('data-close-modal', false);
        // data-open-image appears when avatar is present, so we check for modal attributes
        $response->assertSee('data-modal', false);
    }

    public function test_livewire_component_not_rendered_when_disabled(): void
    {
        config(['digital-business-cards.use_livewire' => false]);
        
        $card = $this->createCard(['lead_form_enabled' => true]);
        
        $response = $this->get('/card/example-card');
        
        $response->assertStatus(200);
        $response->assertDontSee('livewire:lead-form', false);
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
}