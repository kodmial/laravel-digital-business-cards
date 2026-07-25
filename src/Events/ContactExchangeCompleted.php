<?php

namespace DigitalCardKit\Laravel\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class ContactExchangeCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public readonly int $leadId;

    public function __construct(int $leadId)
    {
        $this->leadId = $leadId;
    }
}
