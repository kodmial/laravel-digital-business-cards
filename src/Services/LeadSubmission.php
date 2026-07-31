<?php

namespace DigitalCardKit\Laravel\Services;

use DigitalCardKit\Laravel\Events\ContactExchangeCompleted;
use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Persists a contact lead and its analytics event as one transaction.
 *
 * Lead creation and analytics recording share a transaction; the completion
 * event retains the package's existing after-commit listener semantics.
 */
class LeadSubmission
{
    public function __construct(private readonly EventRecorder $events) {}

    /**
     * Store a validated contact exchange and dispatch its completion event.
     *
     * @param  array<string, mixed>  $attributes
     */
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
