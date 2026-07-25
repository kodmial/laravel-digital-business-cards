<?php

namespace DigitalCardKit\Laravel\Mail;

use DigitalCardKit\Laravel\Models\DigitalBusinessCardLead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactExchangeConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DigitalBusinessCardLead $lead) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->lead->card->lead_confirmation_subject ?: config('digital-business-cards.mail.confirmation_subject'));
    }

    public function content(): Content
    {
        return new Content(view: config(
            'digital-business-cards.mail.confirmation_view',
            'digital-business-cards::emails.contact-exchange-confirmation',
        ));
    }
}
