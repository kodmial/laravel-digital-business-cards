<?php

namespace DigitalCardKit\Laravel\Http\Controllers;

use DigitalCardKit\Laravel\Events\ContactExchangeCompleted;
use DigitalCardKit\Laravel\Services\EventRecorder;
use DigitalCardKit\Laravel\Services\VCardGenerator;
use DigitalCardKit\Laravel\Support\ResolvesModels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DigitalBusinessCardController extends Controller
{
    use ResolvesModels;

    public function __construct(
        private readonly EventRecorder $events,
        private readonly VCardGenerator $vcard,
    ) {}

    public function show(Request $request, string $card): Response
    {
        $cardModel = $this->resolveCard($card);
        abort_unless($cardModel->is_published, 404);

        $this->events->record($request, $cardModel, 'view');

        return response()->view(config('digital-business-cards.card_view', 'digital-business-cards::cards.show'), [
            'card' => $cardModel->load(['blocks' => fn ($query) => $query->where('is_enabled', true)]),
        ]);
    }

    public function download(string $card): Response
    {
        $cardModel = $this->resolveCard($card);
        abort_unless($cardModel->is_published, 404);

        return response($this->vcard->generate($cardModel), 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$cardModel->vcardFilename().'.vcf"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function submitLead(Request $request, string $card): RedirectResponse
    {
        $cardModel = $this->resolveCard($card);
        abort_unless($cardModel->is_published && $cardModel->lead_form_enabled, 404);

        $rules = [];
        foreach ($cardModel->leadFields() as $field) {
            $key = (string) ($field['key'] ?? '');
            if (! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key)) {
                continue;
            }

            $rules[$key] = [($field['required'] ?? false) ? 'required' : 'nullable', 'string', 'max:2000'];
            if (($field['type'] ?? 'text') === 'email') {
                $rules[$key][] = 'email:rfc';
            }
        }
        $rules['consent'] = $cardModel->lead_consent_required
            ? ['required', 'accepted']
            : ['sometimes', 'accepted'];

        $validated = $request->validate($rules);
        unset($validated['consent']);

        $lead = $cardModel->leads()->create([
            'name' => $validated['name'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'company' => $validated['company'] ?? null,
            'comment' => $validated['comment'] ?? null,
            'custom_data' => collect($validated)->except(['name', 'phone', 'email', 'company', 'comment'])->all(),
            'consent_given' => $request->boolean('consent'),
            'source' => Str::limit((string) $request->header('Referer'), 255, ''),
            'submitted_at' => now(),
        ]);

        $this->events->record($request, $cardModel, 'lead');
        ContactExchangeCompleted::dispatch($lead);

        return redirect()
            ->route(config('digital-business-cards.route_name_prefix', 'cards.').'show', $cardModel)
            ->withInput()
            ->with([
                'card_lead_sent' => true,
                'card_confirmation_sent' => $cardModel->lead_send_confirmation
                    && filter_var($lead->email, FILTER_VALIDATE_EMAIL) !== false,
            ]);
    }

    public function event(Request $request, string $card): Response
    {
        $cardModel = $this->resolveCard($card);
        abort_unless($cardModel->is_published, 404);

        $payload = $request->validate([
            'type' => ['required', Rule::in(['share', 'vcard', 'cta', 'contact', 'gallery', 'file', 'video'])],
            'block_id' => ['nullable', 'integer'],
        ]);

        $blockId = $payload['block_id'] ?? null;
        if ($blockId && ! $cardModel->blocks()->where('is_enabled', true)->whereKey($blockId)->exists()) {
            abort(422);
        }

        $this->events->record($request, $cardModel, $payload['type'], $blockId);

        return response()->noContent();
    }
}
