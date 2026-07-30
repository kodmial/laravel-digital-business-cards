<?php

namespace DigitalCardKit\Laravel\Services;

use DigitalCardKit\Laravel\Events\ContactExchangeCompleted;
use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class LeadSubmission
{
    public function __construct(private readonly EventRecorder $events) {}

    /** @param  array<string, mixed>  $attributes */
    public function submit(Request $request, DigitalBusinessCard $card, array $attributes): Model
    {
        $lead = $card->leads()->create($attributes);

        $this->events->record($request, $card, 'lead');
        ContactExchangeCompleted::dispatch($lead->getKey());

        return $lead;
    }
}
