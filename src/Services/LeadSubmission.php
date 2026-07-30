<?php

namespace DigitalCardKit\Laravel\Services;

use DigitalCardKit\Laravel\Events\ContactExchangeCompleted;
use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadSubmission
{
    public function __construct(private readonly EventRecorder $events) {}

    /** @param  array<string, mixed>  $attributes */
    public function submit(Request $request, DigitalBusinessCard $card, array $attributes): Model
    {
        return DB::transaction(function () use ($request, $card, $attributes): Model {
            $lead = $card->leads()->create($attributes);

            $this->events->record($request, $card, 'lead');
            ContactExchangeCompleted::dispatch($lead->getKey());

            return $lead;
        });
    }
}
