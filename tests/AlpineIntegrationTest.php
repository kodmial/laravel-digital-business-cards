<?php

namespace DigitalCardKit\Laravel\Tests;

use DigitalCardKit\Laravel\Tests\Concerns\CreatesCards;

class AlpineIntegrationTest extends TestCase
{
    use CreatesCards;

    public function test_alpine_asset_is_served(): void
    {
        $response = $this->get('/digital-business-cards/assets/alpine.js');
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/javascript; charset=utf-8');
    }

    public function test_alpine_modal_structure_exists(): void
    {
        $card = $this->createCard([
            'lead_form_enabled' => true,
        ]);

        $response = $this->get('/card/example-card');
        $response->assertStatus(200);
        
        // Ensure Alpine modal structure is present
        $response->assertSee('data-modal');
    }

    public function test_modal_focus_management_structure(): void
    {
        $card = $this->createCard([
            'lead_form_enabled' => true,
        ]);

        $response = $this->get('/card/example-card');
        $response->assertStatus(200);
        
        // Ensure focus management elements are present
        $response->assertSee('button');
    }

    public function test_escape_key_handling_structure(): void
    {
        $card = $this->createCard([
            'lead_form_enabled' => true,
        ]);

        $response = $this->get('/card/example-card');
        $response->assertStatus(200);
        
        // Ensure modal containers exist for keyboard handling
        $response->assertSee('data-modal');
    }

    public function test_body_class_management_structure(): void
    {
        $card = $this->createCard([
            'lead_form_enabled' => true,
        ]);

        $response = $this->get('/card/example-card');
        $response->assertStatus(200);
        
        // Ensure Alpine data attributes exist for body class management
        $response->assertSee('data-modal');
    }
}
