<?php

namespace DigitalCardKit\Laravel\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;

class QueueContactExchangeNotifications extends SendContactExchangeNotifications implements ShouldQueue
{
    public bool $afterCommit = true;

    public function viaConnection(): ?string
    {
        return config('digital-business-cards.notifications.queue_connection');
    }

    public function viaQueue(): ?string
    {
        return config('digital-business-cards.notifications.queue_name');
    }
}
