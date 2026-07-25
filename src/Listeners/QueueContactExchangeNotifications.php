<?php

namespace DigitalCardKit\Laravel\Listeners;

use DigitalCardKit\Laravel\Support\Config;
use Illuminate\Contracts\Queue\ShouldQueue;

class QueueContactExchangeNotifications extends SendContactExchangeNotifications implements ShouldQueue
{
    public bool $afterCommit = true;

    public function viaConnection(): ?string
    {
        return Config::get('notifications.queue_connection');
    }

    public function viaQueue(): ?string
    {
        return Config::get('notifications.queue_name');
    }
}
