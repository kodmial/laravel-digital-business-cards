<?php

namespace DigitalCardKit\Laravel\Listeners;

use DigitalCardKit\Laravel\Events\ContactExchangeCompleted;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardLead;
use DigitalCardKit\Laravel\Notifications\NotificationSender;
use DigitalCardKit\Laravel\Support\Config;

class SendContactExchangeNotifications
{
    public function __construct(private readonly NotificationSender $sender) {}

    public function handle(ContactExchangeCompleted $event): void
    {
        /** @var class-string<DigitalBusinessCardLead> $leadModel */
        $leadModel = Config::model('lead');
        $lead = $leadModel::findOrFail($event->leadId);
        $this->sender->sendContactExchange($lead);
    }
}
