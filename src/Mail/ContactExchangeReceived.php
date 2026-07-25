<?php

namespace DigitalCardKit\Laravel\Mail;

use DigitalCardKit\Laravel\Models\DigitalBusinessCardLead;
use DigitalCardKit\Laravel\Support\Config;
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
        return new Envelope(subject: Config::mailSubject('owner'));
    }

    public function content(): Content
    {
        return new Content(view: Config::mailView('owner'));
    }
}
