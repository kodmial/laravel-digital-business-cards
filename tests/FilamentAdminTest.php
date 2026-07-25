<?php

namespace DigitalCardKit\Laravel\Tests;

use DigitalCardKit\Laravel\DigitalBusinessCardsPlugin;
use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCardLeads\DigitalBusinessCardLeadResource;
use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\DigitalBusinessCardResource;
use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Tests\Concerns\CreatesAdminRecords;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class FilamentAdminTest extends TestCase
{
    use CreatesAdminRecords;

    public function test_testbench_panel_registers_the_plugin_resources_and_admin_routes(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertContains(DigitalBusinessCardsPlugin::class, array_map(
            static fn (object $plugin): string => $plugin::class,
            $panel->getPlugins(),
        ));
        $this->assertContains(DigitalBusinessCardResource::class, $panel->getResources());
        $this->assertContains(DigitalBusinessCardLeadResource::class, $panel->getResources());

        foreach ([
            'filament.admin.resources.digital-business-cards.index',
            'filament.admin.resources.digital-business-cards.create',
            'filament.admin.resources.digital-business-card-leads.index',
            'admin.cards.leads.export',
        ] as $name) {
            $this->assertTrue(Route::has($name), "Missing package admin route [{$name}].");
        }
    }

    public function test_guests_are_redirected_from_all_package_admin_pages(): void
    {
        $card = $this->createAdminCard();
        $lead = $this->createAdminLead($card);

        foreach ([
            '/admin/digital-business-cards',
            '/admin/digital-business-cards/create',
            '/admin/digital-business-cards/'.$card->slug.'/edit',
            '/admin/digital-business-card-leads',
            '/admin/digital-business-card-leads/'.$lead->getKey(),
        ] as $url) {
            $this->get($url)->assertRedirect('/admin/login');
        }
    }

    public function test_resource_urls_use_the_registered_panel_and_slug_route_binding(): void
    {
        $card = $this->createAdminCard();
        $lead = $this->createAdminLead($card);

        $this->assertStringEndsWith('/admin/digital-business-cards', DigitalBusinessCardResource::getUrl('index'));
        $this->assertStringEndsWith('/admin/digital-business-cards/create', DigitalBusinessCardResource::getUrl('create'));
        $this->assertStringEndsWith(
            '/admin/digital-business-cards/'.$card->slug.'/edit',
            DigitalBusinessCardResource::getUrl('edit', ['record' => $card]),
        );
        $this->assertStringEndsWith('/admin/digital-business-card-leads', DigitalBusinessCardLeadResource::getUrl('index'));
        $this->assertStringEndsWith(
            '/admin/digital-business-card-leads/'.$lead->getKey(),
            DigitalBusinessCardLeadResource::getUrl('view', ['record' => $lead]),
        );
    }

    public function test_card_resource_backing_data_supports_list_status_counts_and_complete_crud(): void
    {
        $published = $this->createAdminCard(['slug' => 'published-card', 'first_name' => 'Published']);
        $draft = $this->createAdminCard(['slug' => 'draft-card', 'first_name' => 'Draft', 'is_published' => false]);
        $this->createAdminLead($published);

        $listed = DigitalBusinessCard::query()->withCount('leads')->orderBy('first_name')->get();
        $this->assertCount(2, $listed);
        $this->assertTrue($published->is_published);
        $this->assertFalse($draft->is_published);
        $this->assertSame(1, $listed->firstWhere('slug', 'published-card')->leads_count);

        $complete = DigitalBusinessCard::query()->create([
            'slug' => 'complete-card',
            'first_name' => 'Complete',
            'last_name' => 'Example',
            'middle_name' => 'Test',
            'job_title' => 'CEO',
            'company_name' => 'Example Company',
            'headline' => 'Short profile',
            'about' => 'Full profile',
            'background_color' => '#000000',
            'accent_color' => '#ffffff',
            'text_color' => '#cccccc',
            'button_style' => 'pill',
            'font_family' => 'serif',
            'lead_form_enabled' => true,
            'lead_form_title' => 'Contact me',
            'lead_consent_required' => true,
        ]);
        $complete->update(['first_name' => 'Updated', 'job_title' => 'CTO']);

        $this->assertDatabaseHas('digital_business_cards', [
            'slug' => 'complete-card',
            'first_name' => 'Updated',
            'job_title' => 'CTO',
        ]);
        $this->assertSame('pill', $complete->button_style);
        $this->assertSame('serif', $complete->font_family);
        $this->assertFalse($complete->is_published);

        $complete->delete();
        $this->assertDatabaseMissing('digital_business_cards', ['id' => $complete->getKey()]);
    }

    public function test_slug_validation_rules_reject_invalid_and_duplicate_values(): void
    {
        $this->createAdminCard(['slug' => 'taken-slug']);

        $validator = Validator::make(['slug' => 'Not valid'], [
            'slug' => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:digital_business_cards,slug'],
        ]);
        $this->assertTrue($validator->fails());

        $this->expectException(QueryException::class);
        DigitalBusinessCard::query()->create([
            'slug' => 'taken-slug',
            'first_name' => 'Duplicate',
        ]);
    }

    public function test_card_deletion_cascades_and_lead_queries_support_list_details_and_filters(): void
    {
        $first = $this->createAdminCard(['slug' => 'first-card']);
        $second = $this->createAdminCard(['slug' => 'second-card']);
        $block = $first->blocks()->create(['type' => 'text', 'title' => 'Block', 'is_enabled' => true]);
        $event = $first->events()->create([
            'type' => 'view',
            'digital_business_card_block_id' => $block->getKey(),
            'visitor_hash' => 'test-hash',
            'occurred_at' => now(),
        ]);
        $standard = $this->createAdminLead($first);
        $custom = $this->createAdminLead($first, [
            'name' => 'Custom lead',
            'custom_data' => ['telegram' => '@custom'],
        ]);
        $this->createAdminLead($second, ['name' => 'Other card lead']);

        $filtered = config('digital-business-cards.models.lead')::query()
            ->where('digital_business_card_id', $first->getKey())
            ->latest('submitted_at')
            ->get();
        $this->assertCount(2, $filtered);
        $this->assertTrue($filtered->contains($standard));
        $this->assertSame('@custom', $custom->custom_data['telegram']);
        $this->assertSame($first->full_name, $custom->card->full_name);

        $first->delete();
        $this->assertDatabaseMissing('digital_business_card_blocks', ['id' => $block->getKey()]);
        $this->assertDatabaseMissing('digital_business_card_events', ['id' => $event->getKey()]);
        $this->assertDatabaseMissing('digital_business_card_leads', ['id' => $standard->getKey()]);
        $this->assertDatabaseHas('digital_business_card_leads', ['name' => 'Other card lead']);
    }
}
