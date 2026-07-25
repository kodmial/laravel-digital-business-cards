<?php

namespace DigitalCardKit\Laravel\Tests;

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Support\ContactChannelRegistry;
use DigitalCardKit\Laravel\Tests\Concerns\CreatesCards;
use Illuminate\Support\Facades\Storage;

class ModelsMediaAndAssetsTest extends TestCase
{
    use CreatesCards;

    public function test_model_helpers_expose_names_urls_filenames_and_default_or_custom_lead_fields(): void
    {
        $card = $this->createCard();
        $this->assertSame('Alex Taylor Morgan', $card->full_name);
        $this->assertStringEndsWith('/card/example-card', $card->publicUrl());
        $this->assertSame('alex-taylor-morgan', $card->vcardFilename());
        $this->assertCount(5, $card->leadFields());
        $this->assertSame('name', $card->leadFields()[0]['key']);

        $card->update([
            'middle_name' => '',
            'lead_form_fields' => [
                ['key' => 'custom', 'label' => 'Custom field', 'type' => 'text', 'required' => true],
            ],
        ]);
        $this->assertSame('Alex Morgan', $card->fresh()->full_name);
        $this->assertSame('custom', $card->fresh()->leadFields()[0]['key']);
    }

    public function test_contact_registry_normalizes_all_supported_delivery_links(): void
    {
        $cases = [
            [['type' => 'phone', 'value' => '+1 (202) 555-0123'], 'tel:+12025550123'],
            [['type' => 'email', 'value' => 'alex@example.test'], 'mailto:alex@example.test'],
            [['type' => 'telegram', 'value' => '@alex'], 'https://t.me/alex'],
            [['type' => 'whatsapp', 'value' => '+1 202 555 0123'], 'https://wa.me/12025550123'],
            [['type' => 'website', 'value' => 'example.test'], 'https://example.test'],
            [['type' => 'address', 'value' => 'Example Street 1'], 'https://www.google.com/maps/search/'],
        ];

        foreach ($cases as [$contact, $expected]) {
            $this->assertStringStartsWith($expected, ContactChannelRegistry::href($contact));
        }
    }

    public function test_media_uses_configured_storage_urls_and_lightbox_markup(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('cards/avatars/person.jpg', 'image');
        Storage::disk('public')->put('cards/logos/company.png', 'image');
        $card = $this->createCard([
            'avatar' => 'cards/avatars/person.jpg',
            'logo' => 'cards/logos/company.png',
        ]);

        $this->get('/card/example-card')
            ->assertOk()
            ->assertSee($card->storageUrl('cards/avatars/person.jpg'), false)
            ->assertSee($card->storageUrl('cards/logos/company.png'), false)
            ->assertSee('data-open-image', false)
            ->assertSee('data-modal="image"', false)
            ->assertSee('digital-card-logo', false);
    }

    public function test_storage_url_preserves_cdn_origin_and_local_urls_are_same_origin(): void
    {
        config(['filesystems.disks.public.url' => 'https://cdn.example.test/media']);
        $card = new DigitalBusinessCard;
        $this->assertSame(
            'https://cdn.example.test/media/cards/avatars/person.jpg',
            $card->storageUrl('cards/avatars/person.jpg'),
        );

        config(['filesystems.disks.public.url' => '/storage']);
        Storage::forgetDisk('public');
        $this->assertSame('/storage/cards/avatars/person.jpg', $card->storageUrl('cards/avatars/person.jpg'));
    }

    public function test_packaged_assets_support_cache_revalidation_and_contain_expected_interactions(): void
    {
        $cssResponse = $this->get('/digital-business-cards/assets/card.css')
            ->assertOk()
            ->assertHeader('etag')
            ->assertHeader('last-modified');
        $css = $cssResponse->getContent();
        $this->assertStringContainsString('.digital-card-topbar a', $css);
        $this->assertStringContainsString('background: transparent', $css);

        $jsResponse = $this->get('/digital-business-cards/assets/card.js')
            ->assertOk()
            ->assertHeader('etag')
            ->assertHeader('last-modified');
        $js = $jsResponse->getContent();
        $this->assertStringContainsString('data-open-image', $js);

        $this->withHeader('If-None-Match', 'W/'.$cssResponse->headers->get('etag'))
            ->get('/digital-business-cards/assets/card.css')
            ->assertStatus(304)
            ->assertContent('');
        $this->withoutHeader('If-None-Match')
            ->withHeader('If-Modified-Since', $jsResponse->headers->get('last-modified'))
            ->get('/digital-business-cards/assets/card.js')
            ->assertStatus(304)
            ->assertContent('');
    }

    public function test_new_cards_are_drafts_use_effective_configured_theme_and_relationships_work(): void
    {
        $card = DigitalBusinessCard::create(['slug' => 'defaults']);
        $this->assertFalse($card->is_published);
        $this->assertNull($card->background_color);
        $this->assertNull($card->accent_color);
        $this->assertNull($card->text_color);
        $this->assertSame('#101827', $card->themeTokens()['background']);
        $this->assertSame('#7357ff', $card->themeTokens()['accent']);
        $this->assertSame('#ffffff', $card->themeTokens()['text']);

        config([
            'digital-business-cards.default_theme.background' => '#112233',
            'digital-business-cards.default_theme.accent' => '#445566',
            'digital-business-cards.default_theme.text' => '#ddeeff',
        ]);
        $this->assertSame('#112233', $card->themeTokens()['background']);
        $this->assertSame('#445566', $card->themeTokens()['accent']);
        $this->assertSame('#ddeeff', $card->themeTokens()['text']);

        $block = $card->blocks()->create(['type' => 'text', 'is_enabled' => true]);
        $lead = $card->leads()->create(['consent_given' => true, 'submitted_at' => now()]);
        $event = $card->events()->create(['type' => 'cta', 'digital_business_card_block_id' => $block->id, 'occurred_at' => now()]);
        $this->assertTrue($block->card->is($card));
        $this->assertTrue($lead->card->is($card));
        $this->assertTrue($event->card->is($card));
    }
}
