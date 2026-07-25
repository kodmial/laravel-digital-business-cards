<?php

namespace DigitalCardKit\Laravel\Tests;

use DigitalCardKit\Laravel\Tests\Concerns\CreatesCards;

class RateLimitTest extends TestCase
{
    use CreatesCards;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('digital-business-cards.rate_limits', [
            'leads' => ['per_card' => 2, 'per_ip' => 3],
            'events' => ['per_card' => 2, 'per_ip' => 3],
        ]);
    }

    public function test_event_submissions_are_capped_per_card(): void
    {
        $this->createCard();

        $this->postJson('/card/example-card/events', ['type' => 'share'])->assertNoContent();
        $this->postJson('/card/example-card/events', ['type' => 'share'])->assertNoContent();
        $this->postJson('/card/example-card/events', ['type' => 'share'])->assertStatus(429);
    }

    public function test_exhausting_one_card_does_not_lock_a_visitor_out_of_another(): void
    {
        $this->createCard();
        $this->createCard(['slug' => 'second-card']);

        $this->postJson('/card/example-card/events', ['type' => 'share'])->assertNoContent();
        $this->postJson('/card/example-card/events', ['type' => 'share'])->assertNoContent();
        $this->postJson('/card/example-card/events', ['type' => 'share'])->assertStatus(429);

        $this->postJson('/card/second-card/events', ['type' => 'share'])->assertNoContent();
    }

    public function test_the_wider_per_address_cap_bounds_spreading_across_cards(): void
    {
        foreach (['one', 'two', 'three', 'four'] as $slug) {
            $this->createCard(['slug' => $slug]);
        }

        // Three succeed against the per-address budget, each on its own card
        // so the per-card budget is never the limiting factor.
        $this->postJson('/card/one/events', ['type' => 'share'])->assertNoContent();
        $this->postJson('/card/two/events', ['type' => 'share'])->assertNoContent();
        $this->postJson('/card/three/events', ['type' => 'share'])->assertNoContent();
        $this->postJson('/card/four/events', ['type' => 'share'])->assertStatus(429);
    }

    public function test_lead_submissions_are_capped_and_use_their_own_budget(): void
    {
        $this->createCard(['lead_consent_required' => false]);

        $this->post('/card/example-card/contacts', ['name' => 'One'])->assertRedirect();
        $this->post('/card/example-card/contacts', ['name' => 'Two'])->assertRedirect();
        $this->post('/card/example-card/contacts', ['name' => 'Three'])->assertStatus(429);

        // The event limiter is a separate budget and is still untouched.
        $this->postJson('/card/example-card/events', ['type' => 'share'])->assertNoContent();
    }

    public function test_reading_a_card_is_not_rate_limited(): void
    {
        $this->createCard();

        foreach (range(1, 6) as $ignored) {
            $this->get('/card/example-card')->assertOk();
        }
    }
}
