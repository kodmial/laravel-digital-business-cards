<?php

namespace DigitalCardKit\Laravel\Mail;

use DigitalCardKit\Laravel\Models\DigitalBusinessCardLead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactExchangeReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DigitalBusinessCardLead $lead) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('digital-business-cards.mail.owner_subject'));
    }

    public function content(): Content
    {
        return new Content(view: config(
            'digital-business-cards.mail.owner_view',
            'digital-business-cards::emails.contact-exchange-received',
        ));
    }
}
