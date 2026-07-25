<?php

namespace DigitalCardKit\Laravel\Notifications;

use DigitalCardKit\Laravel\Mail\ContactExchangeConfirmation;
use DigitalCardKit\Laravel\Mail\ContactExchangeReceived;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardLead;
use DigitalCardKit\Laravel\Support\Config;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Mail;

class LaravelMailNotificationSender implements NotificationSender
{
    public function sendContactExchange(DigitalBusinessCardLead $lead): void
    {
        if (! $lead->consent_given) {
            return;
        }

        $lead->loadMissing('card');
        $ownerRecipients = collect($lead->card->lead_notification_emails ?: [])
            ->filter(static fn (mixed $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();

        if ($ownerRecipients !== []) {
            $this->mailTo($ownerRecipients)->send(new ContactExchangeReceived($lead));
        }

        if ($lead->card->lead_send_confirmation && filter_var($lead->email, FILTER_VALIDATE_EMAIL) !== false) {
            $this->mailTo($lead->email)->send(new ContactExchangeConfirmation($lead));
        }
    }

    private function mailTo(array|string $recipients): PendingMail
    {
        $mailer = Config::get('mail.mailer');

        return $mailer ? Mail::mailer($mailer)->to($recipients) : Mail::to($recipients);
    }
}
