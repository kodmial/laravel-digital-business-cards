<?php

namespace DigitalCardKit\Laravel\Tests;

use DigitalCardKit\Laravel\DigitalBusinessCardsPlugin;
use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCardLeads\DigitalBusinessCardLeadResource;
use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\DigitalBusinessCardResource;
use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Support\ContactChannelRegistry;
use DigitalCardKit\Laravel\Tests\Fixtures\CustomDigitalBusinessCard;
use DigitalCardKit\Laravel\Tests\Fixtures\CustomDigitalBusinessCardLead;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PackageTest extends TestCase
{
    public function test_filament_plugin_and_resources_use_package_models(): void
    {
        $this->assertSame('digital-business-cards', DigitalBusinessCardsPlugin::make()->getId());
        $this->assertSame(
            config('digital-business-cards.models.card'),
            DigitalBusinessCardResource::getModel(),
        );
        $this->assertSame(
            config('digital-business-cards.models.lead'),
            DigitalBusinessCardLeadResource::getModel(),
        );
    }

    public function test_routes_and_filament_resources_respect_custom_model_subclasses(): void
    {
        config([
            'digital-business-cards.models.card' => CustomDigitalBusinessCard::class,
            'digital-business-cards.models.lead' => CustomDigitalBusinessCardLead::class,
        ]);
        $card = CustomDigitalBusinessCard::create([
            'slug' => 'custom-model',
            'first_name' => 'Custom',
            'is_published' => true,
        ]);
        $lead = $card->leads()->create([
            'name' => 'Custom model lead',
            'consent_given' => true,
            'submitted_at' => now(),
        ]);

        $this->assertSame(CustomDigitalBusinessCard::class, DigitalBusinessCardResource::getModel());
        $this->assertSame(CustomDigitalBusinessCardLead::class, DigitalBusinessCardLeadResource::getModel());
        $this->assertInstanceOf(CustomDigitalBusinessCardLead::class, $lead);
        $this->assertSame($card->getKey(), $lead->digital_business_card_id);
        $this->get('/card/custom-model')->assertOk()->assertSee('Custom');
    }

    public function test_cards_are_private_by_default_and_public_when_published(): void
    {
        $draft = DigitalBusinessCard::create(['slug' => 'draft', 'first_name' => 'Draft']);
        $published = DigitalBusinessCard::create([
            'slug' => 'published',
            'first_name' => 'Published',
            'is_published' => true,
        ]);

        $this->assertFalse($draft->is_published);
        $this->get('/card/draft')->assertNotFound();
        $this->get('/card/'.$published->slug)->assertOk();
    }

    public function test_address_is_a_public_contact_with_a_maps_link(): void
    {
        $card = new DigitalBusinessCard([
            'contact_methods' => [['type' => 'address', 'value' => 'Example Street 1']],
        ]);

        $this->assertSame('address', $card->publicContactMethods()[0]['type']);
        $this->assertStringContainsString(
            'query=Example%20Street%201',
            ContactChannelRegistry::href($card->publicContactMethods()[0]),
        );
    }

    public function test_public_card_view_renders_media_and_mixed_contacts(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('cards/avatars/person.jpg', 'avatar');
        Storage::disk('public')->put('cards/logos/company.png', 'logo');

        $card = DigitalBusinessCard::create([
            'slug' => 'media-and-contacts',
            'first_name' => 'Alex',
            'last_name' => 'Morgan',
            'company_name' => 'Example Company',
            'is_published' => true,
            'avatar' => 'cards/avatars/person.jpg',
            'logo' => 'cards/logos/company.png',
            'contact_methods' => [
                ['type' => 'phone', 'label' => 'Phone', 'value' => '+12025550123'],
                ['type' => 'telegram', 'label' => 'Telegram', 'value' => '@alex'],
                ['type' => 'max', 'label' => 'MAX', 'value' => 'https://max.ru/alex'],
                ['type' => 'email', 'label' => 'Email', 'value' => 'alex@example.test'],
                ['type' => 'whatsapp', 'label' => 'WhatsApp', 'value' => '+12025550123'],
            ],
        ]);

        $this->get('/card/media-and-contacts')
            ->assertOk()
            ->assertSee($card->storageUrl('cards/avatars/person.jpg'), false)
            ->assertSee($card->storageUrl('cards/logos/company.png'), false)
            ->assertSee('class="digital-card-topbar"', false)
            ->assertSee('digital-card-social-row--multiple', false)
            ->assertSee('digital-card-social-row--single', false);
    }

    public function test_logo_link_has_no_light_plaque_in_the_packaged_stylesheet(): void
    {
        $css = $this->get('/digital-business-cards/assets/card.css')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/\.digital-card-topbar a\s*\{[^}]*border:\s*0;[^}]*background:\s*transparent;[^}]*box-shadow:\s*none;[^}]*\}/s',
            $css,
        );
        $this->assertStringNotContainsString(
            '.digital-card-page--dark .digital-card-topbar a',
            $css,
        );
        $this->assertStringNotContainsString(
            '.digital-card-page--light .digital-card-topbar a',
            $css,
        );
    }

    public function test_initial_migration_completes_a_partial_install_without_replacing_card_data(): void
    {
        Schema::dropIfExists('digital_business_card_events');
        Schema::dropIfExists('digital_business_card_leads');
        Schema::dropIfExists('digital_business_card_blocks');

        DB::table('digital_business_cards')->insert([
            'slug' => 'existing-card',
            'first_name' => 'Existing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require __DIR__.'/../database/migrations/2026_07_22_150000_create_digital_business_cards_tables.php';
        $migration->up();

        $this->assertTrue(Schema::hasTable('digital_business_cards'));
        $this->assertTrue(Schema::hasTable('digital_business_card_blocks'));
        $this->assertTrue(Schema::hasTable('digital_business_card_leads'));
        $this->assertTrue(Schema::hasTable('digital_business_card_events'));
        $this->assertDatabaseHas('digital_business_cards', [
            'slug' => 'existing-card',
            'first_name' => 'Existing',
        ]);
    }

    public function test_reconcile_migration_adds_missing_optional_columns_without_replacing_data(): void
    {
        Schema::dropIfExists('digital_business_card_events');
        Schema::dropIfExists('digital_business_card_leads');
        Schema::dropIfExists('digital_business_card_blocks');
        Schema::drop('digital_business_cards');
        Schema::create('digital_business_cards', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('first_name')->nullable();
            $table->timestamps();
        });
        DB::table('digital_business_cards')->insert([
            'slug' => 'legacy-card',
            'first_name' => 'Legacy',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $initial = require __DIR__.'/../database/migrations/2026_07_22_150000_create_digital_business_cards_tables.php';
        $initial->up();
        $reconcile = require __DIR__.'/../database/migrations/2026_07_24_130000_reconcile_digital_business_cards_columns.php';
        $reconcile->up();

        foreach ([
            'is_published', 'last_name', 'middle_name', 'job_title', 'company_name',
            'avatar', 'logo', 'cover_image', 'headline', 'about', 'contact_methods',
            'background_color', 'accent_color', 'text_color', 'theme_mode',
            'button_style', 'font_family', 'lead_form_enabled', 'lead_form_title',
            'lead_form_description', 'lead_form_fields', 'lead_notification_emails',
            'lead_send_confirmation', 'lead_confirmation_subject', 'privacy_url',
            'lead_consent_required', 'meta_title', 'meta_description',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('digital_business_cards', $column), "Missing reconciled column [{$column}].");
        }
        $this->assertDatabaseHas('digital_business_cards', [
            'slug' => 'legacy-card',
            'first_name' => 'Legacy',
        ]);
    }

    public function test_migration_basenames_and_table_names_are_stable(): void
    {
        $migrationDirectory = __DIR__.'/../database/migrations';

        $this->assertFileExists($migrationDirectory.'/2026_07_22_150000_create_digital_business_cards_tables.php');
        $this->assertFileExists($migrationDirectory.'/2026_07_24_130000_reconcile_digital_business_cards_columns.php');
        $this->assertSame([
            'digital_business_card_blocks',
            'digital_business_card_events',
            'digital_business_card_leads',
            'digital_business_cards',
        ], collect([
            'digital_business_cards',
            'digital_business_card_blocks',
            'digital_business_card_leads',
            'digital_business_card_events',
        ])->filter(fn (string $table): bool => Schema::hasTable($table))->sort()->values()->all());
    }

    public function test_configured_privacy_url_is_linked_in_the_consent_copy(): void
    {
        config(['digital-business-cards.privacy_url' => 'https://privacy.example.test/policy']);
        DigitalBusinessCard::create([
            'slug' => 'configured-privacy',
            'first_name' => 'Alex',
            'is_published' => true,
        ]);

        $this->get('/card/configured-privacy')
            ->assertOk()
            ->assertSee('href="https://privacy.example.test/policy"', false);
    }

    public function test_missing_privacy_url_renders_consent_without_a_fallback_link(): void
    {
        config(['digital-business-cards.privacy_url' => null]);
        DigitalBusinessCard::create([
            'slug' => 'plain-consent',
            'first_name' => 'Alex',
            'is_published' => true,
            'privacy_url' => '',
        ]);

        $this->get('/card/plain-consent')
            ->assertOk()
            ->assertSee('I consent to the processing of my personal data')
            ->assertDontSee('согласно <a href=', false)
            ->assertDontSee('href="/"', false);
    }
}
