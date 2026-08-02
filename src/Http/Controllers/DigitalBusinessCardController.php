<?php

namespace DigitalCardKit\Laravel\Http\Controllers;

use DigitalCardKit\Laravel\Http\Requests\StoreCardEventRequest;
use DigitalCardKit\Laravel\Http\Requests\StoreCardLeadRequest;
use DigitalCardKit\Laravel\Services\EventRecorder;
use DigitalCardKit\Laravel\Services\LeadSubmission;
use DigitalCardKit\Laravel\Services\VCardGenerator;
use DigitalCardKit\Laravel\Support\Config;
use DigitalCardKit\Laravel\Support\ResolvesModels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class DigitalBusinessCardController extends Controller
{
    use ResolvesModels;

    public function __construct(
        private readonly EventRecorder $events,
        private readonly VCardGenerator $vcard,
        private readonly LeadSubmission $leadSubmission,
    ) {}

    public function show(Request $request, string $card): Response
    {
        $cardModel = $this->resolvePublishedCard($card);

        $this->events->record($request, $cardModel, 'view');

        return response()->view(Config::cardView(), [
            'card' => $cardModel->load(['blocks' => fn ($query) => $query->where('is_enabled', true)]),
        ]);
    }

    public function download(string $card): Response
    {
        $cardModel = $this->resolvePublishedCard($card);

        return response($this->vcard->generate($cardModel), 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$cardModel->vcardFilename().'.vcf"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function submitLead(StoreCardLeadRequest $request): RedirectResponse
    {
        $card = $request->card();
        $lead = $this->leadSubmission->submit($request, $card, $request->leadAttributes());

        return redirect()
            ->route(Config::routeName('show'), $card)
            ->withInput()
            ->with([
                'card_lead_sent' => true,
                'card_confirmation_sent' => $card->lead_send_confirmation
                    && filter_var($lead->email, FILTER_VALIDATE_EMAIL) !== false,
            ]);
    }

    public function event(StoreCardEventRequest $request): Response
    {
        $this->events->record(
            $request,
            $request->card(),
            $request->validated()['type'],
            $request->blockId(),
        );

        return response()->noContent();
    }
}
