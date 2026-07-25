<?php

namespace DigitalCardKit\Laravel\Tests;

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardBlock;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardEvent;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardLead;
use DigitalCardKit\Laravel\Tests\Fixtures\CustomDigitalBusinessCard;

class FactoryTest extends TestCase
{
    public function test_cards_are_created_as_drafts_and_states_flip_publication(): void
    {
        $this->assertFalse(DigitalBusinessCard::factory()->create()->is_published);
        $this->assertTrue(DigitalBusinessCard::factory()->published()->create()->is_published);
        $this->assertFalse(DigitalBusinessCard::factory()->published()->draft()->create()->is_published);
        $this->assertFalse(DigitalBusinessCard::factory()->withoutLeadForm()->create()->lead_form_enabled);
    }

    public function test_generated_cards_are_independently_addressable_and_renderable(): void
    {
        $cards = DigitalBusinessCard::factory()->published()->count(3)->create();

        $this->assertCount(3, $cards->pluck('slug')->unique());

        foreach ($cards as $card) {
            $this->get('/card/'.$card->slug)->assertOk();
        }
    }

    public function test_related_factories_attach_to_a_card_and_honour_their_states(): void
    {
        $card = DigitalBusinessCard::factory()->published()->create();

        $block = DigitalBusinessCardBlock::factory()->for($card, 'card')->disabled()->create();
        $gallery = DigitalBusinessCardBlock::factory()
            ->for($card, 'card')
            ->gallery(['cards/galleries/one.jpg'])
            ->create();
        $lead = DigitalBusinessCardLead::factory()->for($card, 'card')->withoutConsent()->create();
        $event = DigitalBusinessCardEvent::factory()->for($card, 'card')->ofType('cta')->create();

        $this->assertFalse($block->is_enabled);
        $this->assertSame(['cards/galleries/one.jpg'], $gallery->data['gallery']);
        $this->assertFalse($lead->consent_given);
        $this->assertSame('cta', $event->type);
        $this->assertTrue($card->is($lead->card));
        $this->assertSame(2, $card->blocks()->count());
    }

    public function test_a_factory_without_a_parent_creates_its_own_card(): void
    {
        $lead = DigitalBusinessCardLead::factory()->create();

        $this->assertInstanceOf(DigitalBusinessCard::class, $lead->card);
        $this->assertDatabaseCount('digital_business_cards', 1);
    }

    public function test_factories_resolve_the_configured_model_subclass(): void
    {
        config(['digital-business-cards.models.card' => CustomDigitalBusinessCard::class]);

        $card = DigitalBusinessCard::factory()->create();

        $this->assertInstanceOf(CustomDigitalBusinessCard::class, $card);
    }
}
