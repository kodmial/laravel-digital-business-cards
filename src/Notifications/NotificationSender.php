<?php

namespace DigitalCardKit\Laravel\Notifications;

use DigitalCardKit\Laravel\Models\DigitalBusinessCardLead;

interface NotificationSender
{
    public function sendContactExchange(DigitalBusinessCardLead $lead): void;
}
