<?php

namespace DigitalCardKit\Laravel\Notifications;

use DigitalCardKit\Laravel\Mail\ContactExchangeReceived as ContactExchangeReceivedMailable;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardLead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ContactExchangeReceived extends Notification
{
    use Queueable;

    public function __construct(public DigitalBusinessCardLead $lead) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): ContactExchangeReceivedMailable
    {
        return new ContactExchangeReceivedMailable($this->lead);
    }
}
