<?php

namespace DigitalCardKit\Laravel\Tests;

use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\Pages\CreateDigitalBusinessCard;
use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\Pages\EditDigitalBusinessCard;
use DigitalCardKit\Laravel\Livewire\ContactExchangeForm;
use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Support\Config;
use DigitalCardKit\Laravel\Tests\Concerns\CreatesAdminRecords;
use Filament\Schemas\Components\Wizard;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Sabre\VObject\Reader;

class AdminCardLifecycleTest extends TestCase
{
    use CreatesAdminRecords;

    public function test_create_wizard_next_actions_can_skip_steps_and_final_submission_validates_profile(): void
    {
        $admin = $this->createAdminUser();

        $component = Livewire::actingAs($admin)->test(CreateDigitalBusinessCard::class);
        $page = $component->instance();
        $this->assertInstanceOf(CreateDigitalBusinessCard::class, $page);

        $schema = $page->getSchema('form');
        $this->assertNotNull($schema);

        $wizard = $schema->getComponent(
            static fn ($component): bool => $component instanceof Wizard,
        );
        $this->assertInstanceOf(Wizard::class, $wizard);
        $this->assertTrue($wizard->isSkippable());

        foreach (range(0, 3) as $currentStepIndex) {
            $component
                ->call('callSchemaComponentMethod', $wizard->getKey(), 'nextStep', [$currentStepIndex])
                ->assertDispatched('next-wizard-step', key: $wizard->getKey())
                ->assertHasNoErrors();
        }

        $component
            ->call('create')
            ->assertHasErrors([
                'data.slug' => 'required',
                'data.first_name' => 'required',
            ]);

        $this->assertDatabaseCount('digital_business_cards', 0);
    }

    public function test_admin_can_create_edit_and_publish_a_complete_working_card(): void
    {
        Mail::fake();

        $admin = $this->createAdminUser();

        Livewire::actingAs($admin)
            ->test(CreateDigitalBusinessCard::class)
            ->set('data', $this->creationData())
            ->call('create')
            ->assertHasNoErrors();

        $card = DigitalBusinessCard::query()
            ->where('slug', 'integration-card')
            ->firstOrFail();

        $this->assertTrue((bool) $card->getAttribute('is_published'));
        $this->assertCount(2, $card->blocks()->get());

        Livewire::actingAs($admin)
            ->test(EditDigitalBusinessCard::class, ['record' => $card->getRouteKey()])
            ->set('data.first_name', 'Updated')
            ->set('data.headline', 'Updated public headline')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('previewVersion', 1);

        $card->refresh();

        $this->get($card->publicUrl())
            ->assertOk()
            ->assertSee('Updated Lifecycle')
            ->assertSee('Updated public headline')
            ->assertSee('lifecycle@example.test')
            ->assertSee('About this card')
            ->assertSee('Created through the Filament wizard')
            ->assertSee('Open the portfolio');

        $vcardResponse = $this->get(route(Config::routeName('download'), $card))
            ->assertOk()
            ->assertHeader('content-type', 'text/vcard; charset=utf-8')
            ->assertDownload('updated-lifecycle.vcf');

        $vcard = Reader::read($vcardResponse->getContent());
        $this->assertSame('Updated Lifecycle', (string) $vcard->select('FN')[0]);
        $this->assertSame('lifecycle@example.test', (string) $vcard->select('EMAIL')[0]);

        Livewire::test(ContactExchangeForm::class, ['card' => $card])
            ->set('fields.name', 'Interested visitor')
            ->set('fields.phone', '+1 202 555 0199')
            ->set('fields.email', 'visitor@example.test')
            ->set('consent', true)
            ->call('submit')
            ->assertSet('submitted', true)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('digital_business_card_leads', [
            'digital_business_card_id' => $card->getKey(),
            'name' => 'Interested visitor',
            'phone' => '+1 202 555 0199',
            'email' => 'visitor@example.test',
            'consent_given' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function creationData(): array
    {
        return [
            'slug' => 'integration-card',
            'is_published' => true,
            'first_name' => 'Initial',
            'last_name' => 'Lifecycle',
            'job_title' => 'Integration tester',
            'company_name' => 'Example Company',
            'headline' => 'Initial public headline',
            'about' => 'A card created through the real admin flow.',
            'contact_methods' => [
                ['type' => 'phone', 'label' => 'Phone', 'value' => '+1 202 555 0123'],
                ['type' => 'email', 'label' => 'Email', 'value' => 'lifecycle@example.test'],
                ['type' => 'website', 'label' => 'Website', 'value' => 'https://example.test'],
            ],
            'blocks' => [
                [
                    'type' => 'text',
                    'title' => 'About this card',
                    'content' => 'Created through the Filament wizard',
                    'is_enabled' => true,
                ],
                [
                    'type' => 'link',
                    'title' => 'Portfolio',
                    'url' => 'https://example.test/portfolio',
                    'button_label' => 'Open the portfolio',
                    'data' => ['open_in_new_tab' => true],
                    'is_enabled' => true,
                ],
            ],
            'theme_mode' => 'custom',
            'background_color' => '#0f172a',
            'accent_color' => '#2563eb',
            'text_color' => '#f8fafc',
            'button_style' => 'pill',
            'font_family' => 'system',
            'meta_title' => 'Lifecycle card',
            'meta_description' => 'Integration lifecycle test card',
            'lead_form_enabled' => true,
            'lead_form_title' => 'Share your details',
            'lead_form_description' => 'The card owner will contact you.',
            'lead_form_fields' => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                ['key' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => true],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => false],
            ],
            'lead_notification_emails' => ['owner@example.test'],
            'lead_send_confirmation' => false,
            'lead_consent_required' => true,
            'privacy_url' => 'https://example.test/privacy',
        ];
    }
}
