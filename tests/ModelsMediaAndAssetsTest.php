<?php

namespace DigitalCardKit\Laravel\Tests;

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Support\ContactChannelRegistry;
use DigitalCardKit\Laravel\Support\Css;
use DigitalCardKit\Laravel\Tests\Concerns\CreatesCards;
use DigitalCardKit\Laravel\Tests\Fixtures\CustomDigitalBusinessCard;
use DigitalCardKit\Laravel\Tests\Fixtures\CustomDigitalBusinessCardBlock;
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
            ->assertSee('x-on:click="open(\'image\')"', false)
            ->assertDontSee('data-open-image', false)
            ->assertSee('data-modal="image"', false)
            ->assertSee('digital-card-logo', false);
    }

    public function test_replacing_card_media_deletes_only_the_previous_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('cards/avatars/old.jpg', 'image');
        Storage::disk('public')->put('cards/avatars/new.jpg', 'image');
        Storage::disk('public')->put('cards/logos/kept.png', 'image');
        $card = $this->createCard(['avatar' => 'cards/avatars/old.jpg', 'logo' => 'cards/logos/kept.png']);

        $card->update(['avatar' => 'cards/avatars/new.jpg']);

        Storage::disk('public')->assertMissing('cards/avatars/old.jpg');
        Storage::disk('public')->assertExists('cards/avatars/new.jpg');
        Storage::disk('public')->assertExists('cards/logos/kept.png');
    }

    public function test_updating_a_card_without_touching_media_keeps_every_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('cards/avatars/person.jpg', 'image');
        $card = $this->createCard(['avatar' => 'cards/avatars/person.jpg']);

        $card->update(['job_title' => 'Head of design']);

        Storage::disk('public')->assertExists('cards/avatars/person.jpg');
    }

    public function test_deleting_a_card_removes_its_media_and_the_media_of_its_blocks(): void
    {
        Storage::fake('public');
        foreach (['cards/avatars/a.jpg', 'cards/logos/l.png', 'cards/covers/c.jpg', 'cards/content/f.pdf', 'cards/galleries/g1.jpg', 'cards/galleries/g2.jpg'] as $path) {
            Storage::disk('public')->put($path, 'file');
        }
        $card = $this->createCard([
            'avatar' => 'cards/avatars/a.jpg',
            'logo' => 'cards/logos/l.png',
            'cover_image' => 'cards/covers/c.jpg',
        ]);
        $card->blocks()->create([
            'type' => 'gallery',
            'is_enabled' => true,
            'data' => ['media' => 'cards/content/f.pdf', 'gallery' => ['cards/galleries/g1.jpg', 'cards/galleries/g2.jpg']],
        ]);

        $card->delete();

        foreach (['cards/avatars/a.jpg', 'cards/logos/l.png', 'cards/covers/c.jpg', 'cards/content/f.pdf', 'cards/galleries/g1.jpg', 'cards/galleries/g2.jpg'] as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    public function test_editing_a_block_gallery_deletes_only_the_removed_images(): void
    {
        Storage::fake('public');
        foreach (['cards/galleries/keep.jpg', 'cards/galleries/drop.jpg', 'cards/galleries/add.jpg'] as $path) {
            Storage::disk('public')->put($path, 'image');
        }
        $block = $this->createCard()->blocks()->create([
            'type' => 'gallery',
            'is_enabled' => true,
            'data' => ['gallery' => ['cards/galleries/keep.jpg', 'cards/galleries/drop.jpg']],
        ]);

        $block->update(['data' => ['gallery' => ['cards/galleries/keep.jpg', 'cards/galleries/add.jpg']]]);

        Storage::disk('public')->assertExists('cards/galleries/keep.jpg');
        Storage::disk('public')->assertExists('cards/galleries/add.jpg');
        Storage::disk('public')->assertMissing('cards/galleries/drop.jpg');
    }

    public function test_media_cleanup_applies_to_a_configured_model_subclass(): void
    {
        // Eloquent keys model events by the runtime class, so an observer
        // attached to the packaged class alone would never fire for a host
        // subclass and every replaced upload would be orphaned.
        config(['digital-business-cards.models.card' => CustomDigitalBusinessCard::class]);
        Storage::fake('public');
        Storage::disk('public')->put('cards/avatars/old.jpg', 'image');
        Storage::disk('public')->put('cards/avatars/new.jpg', 'image');
        Storage::disk('public')->put('cards/covers/cover.jpg', 'image');

        $card = CustomDigitalBusinessCard::create([
            'slug' => 'subclassed',
            'avatar' => 'cards/avatars/old.jpg',
            'cover_image' => 'cards/covers/cover.jpg',
        ]);
        $card->update(['avatar' => 'cards/avatars/new.jpg']);

        Storage::disk('public')->assertMissing('cards/avatars/old.jpg');
        Storage::disk('public')->assertExists('cards/avatars/new.jpg');

        $card->delete();

        Storage::disk('public')->assertMissing('cards/avatars/new.jpg');
        Storage::disk('public')->assertMissing('cards/covers/cover.jpg');
    }

    public function test_block_media_cleanup_applies_to_a_configured_model_subclass(): void
    {
        config(['digital-business-cards.models.block' => CustomDigitalBusinessCardBlock::class]);
        Storage::fake('public');
        Storage::disk('public')->put('cards/galleries/drop.jpg', 'image');
        Storage::disk('public')->put('cards/galleries/keep.jpg', 'image');

        $block = $this->createCard()->blocks()->create([
            'type' => 'gallery',
            'is_enabled' => true,
            'data' => ['gallery' => ['cards/galleries/drop.jpg', 'cards/galleries/keep.jpg']],
        ]);

        $this->assertInstanceOf(CustomDigitalBusinessCardBlock::class, $block);

        $block->update(['data' => ['gallery' => ['cards/galleries/keep.jpg']]]);

        Storage::disk('public')->assertMissing('cards/galleries/drop.jpg');
        Storage::disk('public')->assertExists('cards/galleries/keep.jpg');
    }

    public function test_media_cleanup_follows_the_configured_disk(): void
    {
        Storage::fake('public');
        Storage::fake('media');
        Storage::disk('media')->put('cards/avatars/person.jpg', 'image');
        Storage::disk('public')->put('cards/avatars/person.jpg', 'image');
        config(['digital-business-cards.storage_disk' => 'media']);
        $card = $this->createCard(['avatar' => 'cards/avatars/person.jpg']);

        $card->delete();

        Storage::disk('media')->assertMissing('cards/avatars/person.jpg');
        Storage::disk('public')->assertExists('cards/avatars/person.jpg');
    }

    public function test_contact_methods_are_normalized_by_the_cast_on_write_and_read_back_as_a_list(): void
    {
        $card = $this->createCard(['contact_methods' => [
            5 => ['type' => 'telegram', 'label' => 'TG', 'value' => 't.me/handle'],
            9 => ['type' => 'website', 'label' => 'Site', 'value' => 'example.test'],
        ]]);

        $stored = json_decode($card->getAttributes()['contact_methods'], true);
        $this->assertSame([0, 1], array_keys($stored));
        $this->assertSame('https://t.me/handle', $stored[0]['value']);
        $this->assertSame('https://example.test', $stored[1]['value']);
        $this->assertSame($stored, $card->fresh()->contact_methods);
    }

    public function test_contact_methods_cast_tolerates_null_and_malformed_payloads(): void
    {
        $card = $this->createCard(['contact_methods' => []]);
        $this->assertSame([], $card->fresh()->contact_methods);

        $card->forceFill(['contact_methods' => null])->save();
        $this->assertSame([], $card->fresh()->contact_methods);

        $card->update(['contact_methods' => ['not-an-array', ['type' => 'phone', 'value' => '+12025550123']]]);
        $this->assertCount(1, $card->fresh()->contact_methods);
    }

    public function test_cover_image_paths_cannot_break_out_of_the_hero_background_declaration(): void
    {
        Storage::fake('public');
        $path = "cards/covers/evil');}body{display:none}.x{a:url('x.jpg";
        Storage::disk('public')->put($path, 'image');
        $this->createCard(['cover_image' => $path]);

        $html = $this->get('/card/example-card')->assertOk()->getContent();
        preg_match('/digital-card-hero"\s*style="([^"]*)"/', $html, $matches);
        $this->assertNotEmpty($matches, 'The hero section should carry an inline background declaration.');

        // What the CSS parser finally sees, after the HTML parser has decoded
        // the attribute. Every apostrophe from the path must still be
        // backslash-escaped, leaving exactly the one pair that delimits url().
        $declaration = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        $this->assertSame(2, substr_count(str_replace("\\'", '', $declaration), "'"));
        $this->assertStringEndsWith("x.jpg')", $declaration);
    }

    public function test_css_url_escapes_quotes_and_strips_control_characters(): void
    {
        $this->assertSame("url('https://cdn.example.test/a.jpg')", Css::url('https://cdn.example.test/a.jpg'));
        $this->assertSame("url('a\\'); }body{x:1')", Css::url("a'); }body{x:1"));
        $this->assertSame("url('a\\\\b')", Css::url('a\\b'));
        $this->assertSame("url('ab')", Css::url("a\nb"));
        $this->assertSame("url('')", Css::url(''));
    }

    public function test_visitor_hash_is_an_hmac_keyed_with_the_application_key(): void
    {
        $card = $this->createCard();
        $this->withHeader('User-Agent', 'ExampleAgent/1.0')->get('/card/example-card')->assertOk();

        $event = $card->events()->firstOrFail();
        $key = (string) base64_decode(substr((string) config('app.key'), 7), true);

        $this->assertSame(
            hash_hmac('sha256', '127.0.0.1|ExampleAgent/1.0', $key),
            $event->visitor_hash,
        );
        $this->assertNotSame(
            hash('sha256', '127.0.0.1|ExampleAgent/1.0|'.config('app.key')),
            $event->visitor_hash,
        );
    }

    public function test_visitor_hash_separates_distinct_visitors(): void
    {
        $card = $this->createCard();
        $this->withHeader('User-Agent', 'AgentOne')->get('/card/example-card')->assertOk();
        $this->withHeader('User-Agent', 'AgentTwo')->get('/card/example-card')->assertOk();

        $hashes = $card->events()->pluck('visitor_hash')->all();
        $this->assertCount(2, $hashes);
        $this->assertNotSame($hashes[0], $hashes[1]);
        $this->assertSame(64, strlen((string) $hashes[0]));
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
        $this->assertStringContainsString("root.querySelectorAll('[data-track]')", $js);
        $this->assertStringNotContainsString('data-open-image', $js);

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
