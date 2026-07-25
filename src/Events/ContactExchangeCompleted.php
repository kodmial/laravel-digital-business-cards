<?php

namespace DigitalCardKit\Laravel\Events;

use DigitalCardKit\Laravel\Models\DigitalBusinessCardLead;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContactExchangeCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public readonly DigitalBusinessCardLead $lead;

    public function __construct(DigitalBusinessCardLead $lead)
    {
        // Do not serialize arbitrary relations that a host application may
        // have eager-loaded onto its lead subclass.
        $this->lead = $lead->withoutRelations();
    }
}
