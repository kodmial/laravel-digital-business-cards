<?php

namespace DigitalCardKit\Laravel\Tests;

use DigitalCardKit\Laravel\Events\ContactExchangeCompleted;
use DigitalCardKit\Laravel\Listeners\QueueContactExchangeNotifications;
use DigitalCardKit\Laravel\Listeners\SendContactExchangeNotifications;
use DigitalCardKit\Laravel\Livewire\ContactExchangeForm;
use DigitalCardKit\Laravel\Mail\ContactExchangeConfirmation;
use DigitalCardKit\Laravel\Mail\ContactExchangeReceived;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardBlock;
use DigitalCardKit\Laravel\Notifications\LaravelMailNotificationSender;
use DigitalCardKit\Laravel\Notifications\NotificationSender;
use DigitalCardKit\Laravel\Tests\Concerns\CreatesCards;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

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
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ['key' => 'telegram', 'label' => 'Telegram', 'type' => 'text', 'required' => false],
        ]]);

        $this->post('/card/example-card/contacts', [
            'name' => 'Visitor',
            'email' => 'invalid',
            'consent' => '1',
        ])->assertSessionHasErrors('email');

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
            ->assertSee('wire:model="fields.name"', false)
            ->assertSee('wire:loading.attr="disabled"', false)
            ->assertSee('x-data=', false)
            ->assertSee('x-trap.inert.noscroll', false)
            ->assertSee('x-on:contact-exchange-succeeded.window', false)
            ->assertDontSee('action="/card/example-card/contacts"', false);
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
            ->assertSet('submitted', false);

        $this->assertArrayHasKey('fields.email', $component->get('validationErrors'));

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
            ->assertSet('submitted', false);

        $this->assertArrayHasKey('consent', $component->get('validationErrors'));

        $card->update(['lead_form_enabled' => false]);

        Livewire::test(ContactExchangeForm::class, ['card' => $card])->assertNotFound();
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

    public function test_notification_listener_uses_replaceable_sender_and_event_is_queue_safe(): void
    {
        $lead = $this->createCard()->leads()->create([
            'name' => 'Visitor',
            'consent_given' => true,
            'submitted_at' => now(),
        ])->load('card');
        $sender = \Mockery::mock(NotificationSender::class);
        $sender->shouldReceive('sendContactExchange')->once()
            ->with(\Mockery::on(fn ($actual): bool => $actual->is($lead)));

        $event = new ContactExchangeCompleted($lead->getKey());
        (new SendContactExchangeNotifications($sender))->handle($event);

        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $event);
    }

    public function test_queued_listener_uses_configured_connection_and_queue(): void
    {
        config([
            'digital-business-cards.notifications.queue_connection' => 'redis',
            'digital-business-cards.notifications.queue_name' => 'card-mail',
        ]);
        $listener = new QueueContactExchangeNotifications(\Mockery::mock(NotificationSender::class));

        $this->assertInstanceOf(ShouldQueue::class, $listener);
        $this->assertTrue($listener->afterCommit);
        $this->assertSame('redis', $listener->viaConnection());
        $this->assertSame('card-mail', $listener->viaQueue());
    }

    public function test_mail_sender_notifies_owner_and_optionally_confirms_to_consented_visitor(): void
    {
        Mail::fake();
        $card = $this->createCard(['lead_send_confirmation' => true]);
        $lead = $card->leads()->create([
            'name' => 'Visitor',
            'email' => 'visitor@example.test',
            'consent_given' => true,
            'submitted_at' => now(),
        ]);

        (new LaravelMailNotificationSender)->sendContactExchange($lead);

        Mail::assertSent(ContactExchangeReceived::class, fn ($mail): bool => $mail->hasTo('owner@example.test'));
        Mail::assertSent(ContactExchangeConfirmation::class, fn ($mail): bool => $mail->hasTo('visitor@example.test'));

        Mail::fake();
        $lead->card->update(['lead_send_confirmation' => false]);
        (new LaravelMailNotificationSender)->sendContactExchange($lead->fresh());
        Mail::assertSent(ContactExchangeReceived::class);
        Mail::assertNotSent(ContactExchangeConfirmation::class);
    }

    public function test_mail_sender_rejects_unconsented_or_invalid_delivery_and_views_are_configurable(): void
    {
        Mail::fake();
        $lead = $this->createCard()->leads()->create([
            'name' => 'Visitor',
            'email' => 'not-an-email',
            'consent_given' => false,
            'submitted_at' => now(),
        ]);
        (new LaravelMailNotificationSender)->sendContactExchange($lead);
        Mail::assertNothingSent();

        Mail::fake();
        $lead = $this->createCard([
            'slug' => 'no-owner-recipients',
            'lead_notification_emails' => [],
            'lead_send_confirmation' => false,
        ])->leads()->create([
            'name' => 'Visitor',
            'email' => 'visitor@example.test',
            'consent_given' => true,
            'submitted_at' => now(),
        ]);
        (new LaravelMailNotificationSender)->sendContactExchange($lead);
        Mail::assertNothingSent();

        config([
            'digital-business-cards.mail.confirmation_view' => 'mail.custom-confirmation',
            'digital-business-cards.mail.owner_view' => 'mail.custom-owner',
        ]);
        $this->assertSame('mail.custom-confirmation', (new ContactExchangeConfirmation($lead))->content()->view);
        $this->assertSame('mail.custom-owner', (new ContactExchangeReceived($lead))->content()->view);
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
