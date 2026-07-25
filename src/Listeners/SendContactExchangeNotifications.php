<?php

namespace DigitalCardKit\Laravel\Listeners;

use DigitalCardKit\Laravel\Events\ContactExchangeCompleted;
use DigitalCardKit\Laravel\Notifications\NotificationSender;

class SendContactExchangeNotifications
{
    public function __construct(private readonly NotificationSender $sender) {}

    public function handle(ContactExchangeCompleted $event): void
    {
        $this->sender->sendContactExchange($event->lead);
    }
}
