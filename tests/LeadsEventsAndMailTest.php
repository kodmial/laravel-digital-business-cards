<?php

namespace DigitalCardKit\Laravel\Tests;

use DigitalCardKit\Laravel\Events\ContactExchangeCompleted;
use DigitalCardKit\Laravel\Livewire\ContactExchangeForm;
use DigitalCardKit\Laravel\Mail\ContactExchangeConfirmation;
use DigitalCardKit\Laravel\Mail\ContactExchangeReceived;
use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardBlock;
use DigitalCardKit\Laravel\Services\EventRecorder;
use DigitalCardKit\Laravel\Services\LeadSubmission;
use DigitalCardKit\Laravel\Support\Config;
use DigitalCardKit\Laravel\Support\RateLimits;
use DigitalCardKit\Laravel\Tests\Concerns\CreatesCards;
use DigitalCardKit\Laravel\Tests\Fixtures\RecordLeadMiddleware;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class LeadsEventsAndMailTest extends TestCase
{
    use CreatesCards;

    public function test_event_endpoint_records_supported_types_and_optional_block(): void
    {
        $card = $this->createCard();
        $block = $card->blocks()->create([
            'type' => 'gallery',
            'title' => 'Gallery',
            'is_enabled' => true,
        ]);

        foreach (['vcard', 'contact'] as $type) {
            $this->post('/card/example-card/events', ['type' => $type])->assertNoContent();
            $this->assertDatabaseHas('digital_business_card_events', [
                'digital_business_card_id' => $card->id,
                'type' => $type,
            ]);
        }
        foreach (['cta', 'gallery', 'file', 'video'] as $type) {
            $this->post('/card/example-card/events', [
                'type' => $type,
                'block_id' => $block->id,
            ])->assertNoContent();
            $this->assertDatabaseHas('digital_business_card_events', [
                'digital_business_card_id' => $card->id,
                'digital_business_card_block_id' => $block->id,
                'type' => $type,
            ]);
        }
    }

    public function test_event_endpoint_rejects_invalid_type_block_and_private_card(): void
    {
        $this->createCard();
        $this->post('/card/example-card/events', ['type' => 'invalid'])->assertSessionHasErrors('type');
        $this->post('/card/example-card/events', ['type' => 'cta', 'block_id' => 99999])
            ->assertSessionHasErrors('block_id');

        $this->createCard(['slug' => 'private', 'is_published' => false]);
        $this->post('/card/private/events', ['type' => 'contact'])->assertNotFound();
    }

    public function test_event_endpoint_answers_json_clients_with_422_validation_errors(): void
    {
        $this->createCard();

        // This is the shape the packaged card.js actually sends.
        $this->postJson('/card/example-card/events', ['type' => 'invalid'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
        $this->postJson('/card/example-card/events', ['type' => 'cta', 'block_id' => 99999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('block_id');
    }

    public function test_a_block_belonging_to_another_card_or_disabled_is_not_accepted(): void
    {
        $card = $this->createCard();
        $other = $this->createCard(['slug' => 'other-card']);
        $foreign = DigitalBusinessCardBlock::factory()->for($other, 'card')->create();
        $disabled = DigitalBusinessCardBlock::factory()->for($card, 'card')->create(['is_enabled' => false]);

        $this->postJson('/card/example-card/events', ['type' => 'cta', 'block_id' => $foreign->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('block_id');
        $this->postJson('/card/example-card/events', ['type' => 'cta', 'block_id' => $disabled->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('block_id');
        $this->assertSame(0, $card->events()->count());
    }

    public function test_configured_lead_fields_are_validated_stored_and_dispatched(): void
    {
        Event::fake([ContactExchangeCompleted::class]);
        $card = $this->createCard(['lead_form_fields' => [
            ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['key' => 'email', 'label' => 'Work email', 'type' => 'email', 'required' => true],
            ['key' => 'telegram', 'label' => 'Telegram', 'type' => 'text', 'required' => false],
        ]]);

        $this->post('/card/example-card/contacts', [
            'name' => 'Visitor',
            'email' => 'invalid',
            'consent' => '1',
        ])->assertSessionHasErrors([
            'email' => 'The Work email field must be a valid email address.',
        ]);

        $this->post('/card/example-card/contacts', [
            'name' => 'Visitor',
            'email' => 'visitor@example.test',
            'telegram' => '@visitor',
            'consent' => '1',
        ])->assertRedirect();

        $lead = $card->leads()->firstOrFail();
        $this->assertSame('Visitor', $lead->name);
        $this->assertSame('@visitor', $lead->custom_data['telegram']);
        $this->assertTrue($lead->consent_given);
        $this->assertDatabaseHas('digital_business_card_events', [
            'digital_business_card_id' => $card->id,
            'type' => 'lead',
        ]);
        Event::assertDispatched(ContactExchangeCompleted::class);
    }

    public function test_required_optional_and_explicitly_refused_consent_behave_consistently(): void
    {
        Event::fake([ContactExchangeCompleted::class]);
        $this->createCard();
        $this->post('/card/example-card/contacts', ['name' => 'Visitor'])
            ->assertSessionHasErrors('consent');

        $this->createCard(['slug' => 'optional', 'lead_consent_required' => false]);
        $this->post('/card/optional/contacts', [
            'name' => 'Visitor',
            'phone' => '+1 202 555 0123',
        ])->assertRedirect();
        $this->assertDatabaseHas('digital_business_card_leads', [
            'name' => 'Visitor',
            'consent_given' => false,
        ]);

        $this->createCard(['slug' => 'refused', 'lead_consent_required' => false]);
        $this->post('/card/refused/contacts', ['name' => 'Refused', 'consent' => '0'])
            ->assertSessionHasErrors('consent');
        $this->assertDatabaseMissing('digital_business_card_leads', ['name' => 'Refused']);
    }

    public function test_default_fields_invalid_custom_keys_and_disabled_form_are_safe(): void
    {
        Event::fake([ContactExchangeCompleted::class]);
        $this->createCard(['lead_form_fields' => []]);
        $this->post('/card/example-card/contacts', [
            'name' => 'Default fields',
            'phone' => '+1 202 555 0123',
            'company' => 'Example Company',
            'consent' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('digital_business_card_leads', [
            'name' => 'Default fields',
            'company' => 'Example Company',
        ]);

        $this->createCard([
            'slug' => 'invalid-key',
            'lead_form_fields' => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                ['key' => 'INVALID-KEY!!!', 'label' => 'Ignored', 'type' => 'text', 'required' => true],
            ],
        ]);
        $this->post('/card/invalid-key/contacts', ['name' => 'Valid', 'consent' => '1'])->assertRedirect();

        $this->createCard(['slug' => 'disabled', 'lead_form_enabled' => false]);
        $this->post('/card/disabled/contacts', ['name' => 'No form'])->assertNotFound();
    }

    public function test_forms_render_configured_copy_explicit_consent_and_success_dialog(): void
    {
        $this->createCard([
            'lead_consent_required' => false,
            'lead_form_title' => 'Contact the card owner',
            'lead_form_description' => 'A reply will follow shortly.',
        ]);

        $this->withSession(['card_lead_sent' => true, 'card_confirmation_sent' => true])
            ->get('/card/example-card')
            ->assertOk()
            ->assertSee('Contact the card owner')
            ->assertSee('A reply will follow shortly.')
            ->assertSee('name="consent"', false)
            ->assertSee('data-inline-lead-form', false)
            ->assertSee('data-modal="legacy-success"', false)
            ->assertSee('Your contact details have been sent')
            ->assertSee('>OK<', false);
    }

    public function test_submission_preserves_old_input_and_bounds_referer_source(): void
    {
        Event::fake([ContactExchangeCompleted::class]);
        $card = $this->createCard();
        $referer = 'https://source.example.test/'.str_repeat('a', 400);

        $this->withHeader('Referer', $referer)
            ->post('/card/example-card/contacts', [
                'name' => 'Preserved visitor',
                'phone' => '+1 202 555 0123',
                'email' => 'visitor@example.test',
                'consent' => '1',
            ])
            ->assertRedirect(route('cards.show', $card))
            ->assertSessionHas('card_lead_sent', true)
            ->assertSessionHasInput('name', 'Preserved visitor');

        $this->assertSame(255, strlen($card->leads()->firstOrFail()->source));
    }

    public function test_public_card_renders_livewire_forms_and_alpine_modal_controls(): void
    {
        $this->createCard();

        $this->get('/card/example-card')
            ->assertOk()
            ->assertSee('wire:submit="submit"', false)
            ->assertSee('novalidate', false)
            ->assertSee('wire:blur="validateField(\'name\')"', false)
            ->assertSee('wire:change="validateConsent"', false)
            ->assertSee('wire:blur="validateConsent"', false)
            ->assertSee('wire:loading.attr="disabled"', false)
            ->assertSee('data-track="vcard"', false)
            ->assertSee("window.setTimeout(() => open('exchange'), exchangePromptDelayAfterDownload)", false)
            ->assertSee('x-data=', false)
            ->assertSee('x-trap.inert.noscroll', false)
            ->assertSee('x-on:contact-exchange-succeeded.window', false)
            ->assertDontSee('action="/card/example-card/contacts"', false);
    }

    public function test_livewire_validates_updated_fields_and_uses_their_configured_labels(): void
    {
        $card = $this->createCard([
            'lead_consent_required' => false,
            'lead_form_fields' => [
                ['key' => 'email', 'label' => 'Work email', 'type' => 'email', 'required' => true],
            ],
        ]);

        $component = Livewire::test(ContactExchangeForm::class, ['card' => $card])
            ->call('validateField', 'email')
            ->assertSee('The Work email field is required.')
            ->assertSet('submitted', false);

        $component
            ->set('fields.email', 'invalid')
            ->call('validateField', 'email')
            ->assertSee('The Work email field must be a valid email address.')
            ->assertSet('submitted', false)
            ->set('fields.email', 'visitor@example.test')
            ->call('validateField', 'email')
            ->assertDontSee('The Work email field must be a valid email address.')
            ->assertSet('validationErrors', []);
    }

    public function test_legacy_validation_errors_and_old_input_are_restored_in_livewire_forms(): void
    {
        $this->createCard(['lead_form_fields' => [
            ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
        ]]);

        $this->from('/card/example-card')
            ->post('/card/example-card/contacts', [
                'name' => 'Remembered Visitor',
                'email' => 'invalid-email',
                'consent' => '1',
            ])
            ->assertRedirect('/card/example-card')
            ->assertSessionHasErrors('email');

        $this->get('/card/example-card')
            ->assertOk()
            ->assertSee('Remembered Visitor')
            ->assertSee('The Email field must be a valid email address.')
            ->assertSee("modal: 'exchange'", false);
    }

    public function test_livewire_validates_dynamic_fields_and_preserves_the_submission_pipeline(): void
    {
        Event::fake([ContactExchangeCompleted::class]);
        $card = $this->createCard([
            'lead_send_confirmation' => true,
            'lead_form_fields' => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                ['key' => 'telegram', 'label' => 'Telegram', 'type' => 'text', 'required' => false],
            ],
        ]);

        $component = Livewire::test(ContactExchangeForm::class, ['card' => $card])
            ->set('fields.name', 'Visitor')
            ->set('fields.email', 'invalid')
            ->set('consent', true)
            ->call('submit')
            ->assertSee('valid email address')
            ->assertSet('submitted', false);

        $component->set('fields.email', 'visitor@example.test')
            ->set('fields.telegram', '@visitor')
            ->set('fields.unconfigured', 'ignored')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true)
            ->assertSet('confirmationSent', true);

        $lead = $card->leads()->firstOrFail();
        $this->assertSame('Visitor', $lead->getAttribute('name'));
        $this->assertSame('visitor@example.test', $lead->getAttribute('email'));
        $this->assertSame(['telegram' => '@visitor'], $lead->getAttribute('custom_data'));
        $this->assertTrue($lead->getAttribute('consent_given'));
        $this->assertTrue($component->get('submitted'));
        $this->assertDatabaseHas('digital_business_card_events', [
            'digital_business_card_id' => $card->getKey(),
            'type' => 'lead',
        ]);
        Event::assertDispatched(ContactExchangeCompleted::class);
    }

    public function test_livewire_rejects_missing_consent_and_disabled_forms(): void
    {
        $card = $this->createCard();

        $component = Livewire::test(ContactExchangeForm::class, ['card' => $card])
            ->set('fields.name', 'Visitor')
            ->set('fields.phone', '+1 202 555 0123')
            ->call('submit')
            ->assertSee('consent field is required')
            ->assertSet('submitted', false);

        $card->update(['lead_form_enabled' => false]);

        Livewire::test(ContactExchangeForm::class, ['card' => $card])->assertNotFound();
    }

    public function test_livewire_rejects_explicitly_refused_optional_consent(): void
    {
        $card = $this->createCard(['lead_consent_required' => false]);

        $component = Livewire::test(ContactExchangeForm::class, ['card' => $card])
            ->set('fields.name', 'Visitor')
            ->set('fields.phone', '+1 202 555 0123')
            ->set('consent', false)
            ->call('submit')
            ->assertSee('consent field must be accepted')
            ->assertSet('submitted', false);
        $this->assertSame(0, $card->leads()->count());
    }

    public function test_livewire_uses_the_package_translation_for_invalid_phone_numbers(): void
    {
        app()->setLocale('ru');
        $card = $this->createCard();

        $component = Livewire::test(ContactExchangeForm::class, ['card' => $card])
            ->set('fields.name', 'Visitor')
            ->set('fields.phone', 'not-a-phone-number')
            ->set('consent', true)
            ->call('submit')
            ->assertSee('Поле Телефон должно содержать корректный номер телефона.')
            ->assertSet('submitted', false);
    }

    public function test_livewire_rejects_honeypot_submissions_without_creating_a_lead(): void
    {
        $card = $this->createCard(['lead_consent_required' => false]);

        Livewire::test(ContactExchangeForm::class, ['card' => $card])
            ->set('fields.name', 'Spam bot')
            ->set('fields.phone', '+1 202 555 0123')
            ->set('website', 'https://spam.example')
            ->call('submit')
            ->assertSee('Unable to submit the form')
            ->assertSet('submitted', false);

        $this->assertSame(0, $card->leads()->count());
    }

    public function test_livewire_rejects_submissions_that_are_faster_than_a_human_can_fill_the_form(): void
    {
        config(['digital-business-cards.spam_protection.minimum_fill_seconds' => 3]);
        $card = $this->createCard(['lead_consent_required' => false]);
        $component = Livewire::test(ContactExchangeForm::class, ['card' => $card])
            ->set('fields.name', 'Visitor')
            ->set('fields.phone', '+1 202 555 0123')
            ->call('submit')
            ->assertSee('Unable to submit the form')
            ->assertSet('submitted', false);

        $this->assertSame(0, $card->leads()->count());

        $this->travel(3)->seconds();

        $component->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $this->assertSame(1, $card->leads()->count());
    }

    public function test_livewire_prevents_clients_from_tampering_with_the_spam_timer(): void
    {
        $card = $this->createCard();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(ContactExchangeForm::class, ['card' => $card])
            ->set('formInitializedAt', 0);
    }

    public function test_livewire_submissions_use_the_existing_per_card_rate_limit(): void
    {
        config(['digital-business-cards.rate_limits.leads' => ['per_card' => 2, 'per_ip' => 3]]);
        $card = $this->createCard(['lead_consent_required' => false]);

        foreach (['One', 'Two'] as $name) {
            Livewire::test(ContactExchangeForm::class, ['card' => $card])
                ->set('fields.name', $name)
                ->set('fields.phone', '+1 202 555 0123')
                ->call('submit')
                ->assertSet('submitted', true);
        }

        Livewire::test(ContactExchangeForm::class, ['card' => $card])
            ->set('fields.name', 'Three')
            ->set('fields.phone', '+1 202 555 0123')
            ->call('submit')
            ->assertStatus(429);

        $this->assertSame(2, $card->leads()->count());
    }

    public function test_livewire_submissions_run_the_configured_lead_middleware(): void
    {
        RecordLeadMiddleware::$cards = [];
        RecordLeadMiddleware::$routeNames = [];
        RecordLeadMiddleware::$deny = false;
        config(['digital-business-cards.lead_middleware' => [
            RecordLeadMiddleware::class,
            'throttle:'.RateLimits::LEADS,
        ]]);
        $card = $this->createCard(['lead_consent_required' => false]);

        Livewire::test(ContactExchangeForm::class, ['card' => $card])
            ->set('fields.name', 'Visitor')
            ->set('fields.phone', '+1 202 555 0123')
            ->call('submit')
            ->assertSet('submitted', true);

        $this->assertSame(['example-card'], RecordLeadMiddleware::$cards);
        $this->assertSame([Config::routeName('leads.store')], RecordLeadMiddleware::$routeNames);
    }

    public function test_configured_lead_middleware_can_reject_a_livewire_submission(): void
    {
        RecordLeadMiddleware::$cards = [];
        RecordLeadMiddleware::$routeNames = [];
        RecordLeadMiddleware::$deny = true;
        config(['digital-business-cards.lead_middleware' => [RecordLeadMiddleware::class]]);
        $card = $this->createCard(['lead_consent_required' => false]);

        Livewire::test(ContactExchangeForm::class, ['card' => $card])
            ->set('fields.name', 'Rejected')
            ->set('fields.phone', '+1 202 555 0123')
            ->call('submit')
            ->assertStatus(403);

        $this->assertSame(0, $card->leads()->count());
    }

    public function test_legacy_and_livewire_submissions_share_one_rate_limit_budget(): void
    {
        config(['digital-business-cards.rate_limits.leads' => ['per_card' => 2, 'per_ip' => 3]]);
        $card = $this->createCard(['lead_consent_required' => false]);

        $this->post('/card/example-card/contacts', [
            'name' => 'Legacy',
            'phone' => '+1 202 555 0123',
        ])->assertRedirect();

        Livewire::test(ContactExchangeForm::class, ['card' => $card])
            ->set('fields.name', 'Livewire')
            ->set('fields.phone', '+1 202 555 0123')
            ->call('submit')
            ->assertSet('submitted', true);

        $this->post('/card/example-card/contacts', [
            'name' => 'Over limit',
            'phone' => '+1 202 555 0123',
        ])->assertStatus(429);

        $this->assertSame(2, $card->leads()->count());
    }

    public function test_livewire_rate_limit_reservation_is_serialized_per_address(): void
    {
        $address = '203.0.113.10';
        $lock = Cache::lock('digital-business-cards:lead-rate-limit:'.hash('sha256', $address), 10);
        $this->assertTrue($lock->get());

        try {
            $request = Request::create('/livewire/update', 'POST', server: ['REMOTE_ADDR' => $address]);
            RateLimits::ensureLeadSubmissionIsAllowed($request, 'example-card');
            $this->fail('A concurrent reservation should not bypass the shared rate-limit lock.');
        } catch (TooManyRequestsHttpException $exception) {
            $this->assertSame(1, $exception->getHeaders()['Retry-After']);
        } finally {
            $lock->release();
        }
    }

    public function test_lead_and_analytics_event_are_rolled_back_together(): void
    {
        Event::fake([ContactExchangeCompleted::class]);
        $card = $this->createCard();
        $events = new class extends EventRecorder
        {
            public function record(Request $request, DigitalBusinessCard $card, string $type, ?int $blockId = null): void
            {
                throw new \RuntimeException('Event write failed.');
            }
        };

        try {
            (new LeadSubmission($events))->submit(Request::create('/contacts', 'POST'), $card, [
                'name' => 'Visitor',
                'consent_given' => true,
                'submitted_at' => now(),
            ]);
            $this->fail('The event write failure should abort the submission.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Event write failed.', $exception->getMessage());
        }

        $this->assertSame(0, $card->leads()->count());
        Event::assertNotDispatched(ContactExchangeCompleted::class);
    }

    public function test_package_ships_without_automatic_mail_or_a_default_listener(): void
    {
        Mail::fake();
        $this->createCard(['lead_send_confirmation' => true]);

        $this->assertFalse(Event::hasListeners(ContactExchangeCompleted::class));

        $this->post('/card/example-card/contacts', [
            'name' => 'Visitor',
            'phone' => '+1 202 555 0123',
            'email' => 'visitor@example.test',
            'consent' => '1',
        ])->assertRedirect();

        $this->assertSame(1, Config::model('lead')::count());
        Mail::assertNothingSent();
    }

    public function test_host_listener_sends_the_packaged_mailables_from_the_completion_event(): void
    {
        Mail::fake();
        Event::listen(ContactExchangeCompleted::class, function (ContactExchangeCompleted $event): void {
            $lead = Config::model('lead')::findOrFail($event->leadId);
            $lead->loadMissing('card');

            if ($lead->consent_given && filter_var($lead->email, FILTER_VALIDATE_EMAIL) !== false) {
                Mail::to($lead->email)->send(new ContactExchangeConfirmation($lead));
            }
        });
        $this->createCard(['lead_send_confirmation' => true]);

        $this->post('/card/example-card/contacts', [
            'name' => 'Visitor',
            'phone' => '+1 202 555 0123',
            'email' => 'visitor@example.test',
            'consent' => '1',
        ])->assertRedirect();

        Mail::assertSent(ContactExchangeConfirmation::class, fn ($mail): bool => $mail->hasTo('visitor@example.test'));
    }

    public function test_mailable_helpers_use_configured_subjects_and_views(): void
    {
        config([
            'digital-business-cards.mail.owner_subject' => 'Custom owner subject',
            'digital-business-cards.mail.confirmation_subject' => 'Custom confirmation subject',
            'digital-business-cards.mail.confirmation_view' => 'mail.custom-confirmation',
            'digital-business-cards.mail.owner_view' => 'mail.custom-owner',
        ]);
        $lead = $this->createCard()->leads()->create([
            'name' => 'Visitor',
            'email' => 'visitor@example.test',
            'consent_given' => true,
            'submitted_at' => now(),
        ])->load('card');

        $this->assertSame('Custom owner subject', (new ContactExchangeReceived($lead))->envelope()->subject);
        $this->assertSame('Custom confirmation subject', (new ContactExchangeConfirmation($lead))->envelope()->subject);
        $this->assertSame('mail.custom-owner', (new ContactExchangeReceived($lead))->content()->view);
        $this->assertSame('mail.custom-confirmation', (new ContactExchangeConfirmation($lead))->content()->view);
    }

    public function test_event_is_queue_safe_and_serializes_only_the_lead_identifier(): void
    {
        $lead = $this->createCard()->leads()->create([
            'name' => 'Visitor',
            'consent_given' => true,
            'submitted_at' => now(),
        ]);

        $event = new ContactExchangeCompleted((int) $lead->getKey());

        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $event);
        $this->assertSame($lead->getKey(), $event->leadId);
    }

    public function test_confirmation_email_uses_sanitized_theme_and_neutral_identity(): void
    {
        $card = $this->createCard([
            'theme_mode' => 'custom',
            'background_color' => 'not-a-color',
            'accent_color' => '#e85d3f',
            'text_color' => '#ffffff',
        ]);
        $lead = $card->leads()->create([
            'name' => 'Visitor',
            'email' => 'visitor@example.test',
            'consent_given' => true,
            'submitted_at' => now(),
        ]);

        $html = (new ContactExchangeConfirmation($lead))->render();
        $this->assertStringContainsString('Hello, Visitor', $html);
        $this->assertStringContainsString('Example Studio', $html);
        $this->assertStringContainsString('#e85d3f', $html);
        $this->assertStringContainsString('#0b101a', $html);
        $this->assertStringNotContainsString('not-a-color', $html);
    }
}
