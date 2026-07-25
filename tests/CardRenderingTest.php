<?php

namespace DigitalCardKit\Laravel\Tests;

use DigitalCardKit\Laravel\Tests\Concerns\CreatesCards;

class CardRenderingTest extends TestCase
{
    use CreatesCards;

    public function test_public_route_renders_identity_metadata_optional_content_and_records_view(): void
    {
        $card = $this->createCard();
        $card->blocks()->create([
            'type' => 'link',
            'title' => 'Portfolio',
            'url' => 'https://example.test/portfolio',
            'is_enabled' => true,
        ]);

        $this->get('/card/example-card')
            ->assertOk()
            ->assertSee('Alex Taylor Morgan')
            ->assertSee('Product designer')
            ->assertSee('Example Studio')
            ->assertSee('Independent product design practice.')
            ->assertSee('Alex Morgan — digital card', false)
            ->assertSee('Alex Morgan contact details', false)
            ->assertSee('Portfolio');
        $this->assertDatabaseHas('digital_business_card_events', [
            'digital_business_card_id' => $card->id,
            'type' => 'view',
        ]);
    }

    public function test_empty_optional_sections_and_contacts_are_omitted_while_minimal_card_still_renders(): void
    {
        $this->createCard([
            'first_name' => 'Minimal',
            'last_name' => null,
            'middle_name' => null,
            'company_name' => null,
            'logo' => null,
            'about' => null,
            'contact_methods' => [],
            'lead_form_enabled' => false,
        ]);

        $this->get('/card/example-card')
            ->assertOk()
            ->assertSee('Minimal')
            ->assertSee('Сохранить в контакты')
            ->assertSee('data-modal="save"', false)
            ->assertDontSee('digital-card-company-section', false)
            ->assertDontSee('О компании')
            ->assertDontSee('Обо мне');
    }

    public function test_about_content_is_escaped(): void
    {
        $this->createCard(['about' => "Safe description\n<script>alert('unsafe')</script>"]);

        $this->get('/card/example-card')
            ->assertOk()
            ->assertSee('Safe description')
            ->assertSee('&lt;script&gt;alert(&#039;unsafe&#039;)&lt;/script&gt;', false)
            ->assertDontSee("<script>alert('unsafe')</script>", false);
    }

    public function test_about_heading_matches_a_personal_or_company_only_card(): void
    {
        $this->createCard(['slug' => 'person-about']);
        $this->get('/card/person-about')
            ->assertOk()
            ->assertSee('<h2>Обо мне</h2>', false);

        $this->createCard([
            'slug' => 'company-about',
            'first_name' => null,
            'last_name' => null,
            'middle_name' => null,
            'company_name' => 'Example Company',
        ]);
        $this->get('/card/company-about')
            ->assertOk()
            ->assertSee('<h2>О компании</h2>', false)
            ->assertDontSee('<h2>Обо мне</h2>', false);
    }

    public function test_all_font_and_button_variants_render_their_classes(): void
    {
        foreach (['system', 'serif', 'mono'] as $font) {
            $this->createCard(['slug' => "font-{$font}", 'font_family' => $font]);
            $this->get("/card/font-{$font}")->assertOk()->assertSee("card-font-{$font}", false);
        }

        foreach (['rounded', 'pill', 'square'] as $style) {
            $this->createCard(['slug' => "button-{$style}", 'button_style' => $style]);
            $this->get("/card/button-{$style}")->assertOk()->assertSee("card-button-{$style}", false);
        }
    }

    public function test_custom_theme_variables_are_rendered(): void
    {
        $this->createCard([
            'theme_mode' => 'custom',
            'background_color' => '#101827',
            'accent_color' => '#f97316',
            'text_color' => '#f8fafc',
        ]);

        $this->get('/card/example-card')
            ->assertOk()
            ->assertSee('--card-bg: #101827', false)
            ->assertSee('--card-accent: #f97316', false)
            ->assertSee('--card-text: #f8fafc', false);
    }

    public function test_supported_blocks_render_and_disabled_blocks_do_not(): void
    {
        $card = $this->createCard();
        $blocks = [
            ['type' => 'text', 'title' => 'Biography', 'content' => 'Long-form copy'],
            ['type' => 'link', 'title' => 'Portfolio', 'url' => 'https://example.test', 'button_label' => 'Open portfolio'],
            ['type' => 'social', 'title' => 'Community', 'url' => 'https://social.example.test', 'button_label' => 'Follow'],
            ['type' => 'gallery', 'title' => 'Gallery', 'content' => 'Selected work', 'data' => ['gallery' => ['cards/galleries/one.jpg']]],
            ['type' => 'video', 'title' => 'Presentation', 'content' => 'Product overview', 'url' => 'https://video.example.test/watch'],
            ['type' => 'file', 'title' => 'Resume', 'content' => 'PDF document', 'url' => 'https://files.example.test/resume.pdf'],
            ['type' => 'banner', 'title' => 'Announcement', 'content' => 'New release', 'url' => 'https://example.test/news', 'data' => ['media' => 'cards/content/banner.jpg']],
        ];
        foreach ($blocks as $index => $block) {
            $card->blocks()->create($block + ['is_enabled' => true, 'sort_order' => $index]);
        }
        $card->blocks()->create(['type' => 'text', 'title' => 'Hidden block', 'content' => 'Hidden copy', 'is_enabled' => false]);

        $response = $this->get('/card/example-card')->assertOk();
        foreach (['Biography', 'Open portfolio', 'Follow', 'Gallery', 'Presentation', 'Resume', 'Announcement'] as $copy) {
            $response->assertSee($copy);
        }
        $response
            ->assertSee('Long-form copy')
            ->assertSee('Selected work')
            ->assertSee('cards/galleries/one.jpg', false)
            ->assertSee('Product overview')
            ->assertSee('Смотреть видео')
            ->assertSee('https://files.example.test/resume.pdf', false)
            ->assertSee('PDF document')
            ->assertSee('cards/content/banner.jpg', false)
            ->assertSee('New release')
            ->assertDontSee('Hidden block')
            ->assertDontSee('Hidden copy')
            ->assertDontSee('Portfolio</strong>', false)
            ->assertDontSee('Community</strong>', false);
    }

    public function test_link_and_social_blocks_have_usable_label_fallbacks(): void
    {
        $card = $this->createCard();
        $card->blocks()->create(['type' => 'link', 'title' => 'Title fallback', 'url' => 'https://example.test/one', 'is_enabled' => true]);
        $card->blocks()->create(['type' => 'link', 'button_label' => 'Label fallback', 'url' => 'https://example.test/two', 'is_enabled' => true]);

        $this->get('/card/example-card')
            ->assertOk()
            ->assertSee('Title fallback')
            ->assertSee('Label fallback');
    }
}
