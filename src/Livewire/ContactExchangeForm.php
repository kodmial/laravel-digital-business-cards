<?php

namespace DigitalCardKit\Laravel\Livewire;

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Services\LeadSubmission;
use DigitalCardKit\Laravel\Support\Config;
use DigitalCardKit\Laravel\Support\LeadFormData;
use DigitalCardKit\Laravel\Support\RateLimits;
use DigitalCardKit\Laravel\Support\ResolvesModels;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Livewire-backed contact exchange form for both inline and modal rendering.
 *
 * Alpine owns only modal presentation; validation, submission, rate limiting,
 * and success state remain server-side in this component.
 */
class ContactExchangeForm extends Component
{
    use ResolvesModels;

    /** @var array<string, mixed> */
    public array $fields = [];

    public ?bool $consent = null;

    public bool $submitted = false;

    public bool $confirmationSent = false;

    public string $website = '';

    /** @var array<string, array<int, string>> */
    #[Locked]
    public array $validationErrors = [];

    #[Locked]
    public string $cardRouteKey;

    #[Locked]
    public bool $inline = false;

    #[Locked]
    public int $formInitializedAt;

    public function mount(DigitalBusinessCard $card, bool $inline = false): void
    {
        abort_unless($card->getAttribute('lead_form_enabled'), 404);

        $fieldKeys = array_column($card->validatableLeadFields(), 'key');
        $this->cardRouteKey = (string) $card->getRouteKey();
        $this->inline = $inline;
        $this->formInitializedAt = now()->timestamp;
        $this->fields = array_fill_keys($fieldKeys, '');
        $this->restoreLegacySubmission($fieldKeys);
    }

    public function submit(LeadSubmission $submission, Pipeline $pipeline, Router $router): void
    {
        $card = $this->card();
        $request = request();
        $originalRouteResolver = $request->getRouteResolver();
        $leadRoute = $router->getRoutes()->getByName(Config::routeName('leads.store'));

        throw_unless($leadRoute instanceof Route, new \LogicException('The contact exchange route is not registered.'));

        $leadRoute = clone $leadRoute;
        $leadRoute->bind(Request::create(
            route(Config::routeName('leads.store'), $card, false),
            'POST',
        ));
        $leadRoute->setParameter('card', (string) $card->getRouteKey());
        $request->setRouteResolver(static fn (): Route => $leadRoute);
        $submissionReached = false;

        try {
            $response = $pipeline
                ->send($request)
                ->through($router->resolveMiddleware(Config::middleware(
                    'lead_middleware',
                    ['throttle:'.RateLimits::LEADS],
                )))
                ->then(function (Request $request) use ($card, $submission, &$submissionReached) {
                    $submissionReached = true;
                    $this->performSubmission($submission, $request, $card);

                    return response()->noContent();
                });
        } finally {
            $request->setRouteResolver($originalRouteResolver);
        }

        if ($submissionReached) {
            return;
        }

        if ($response instanceof RedirectResponse) {
            $this->redirect($response->getTargetUrl());

            return;
        }

        throw new HttpResponseException($response);
    }

    public function validateField(string $key): void
    {
        $this->validateLiveAttribute('fields.'.$key);
    }

    public function validateConsent(): void
    {
        $this->validateLiveAttribute('consent');
    }

    private function performSubmission(
        LeadSubmission $submission,
        Request $request,
        DigitalBusinessCard $card,
    ): void {
        if ($this->looksLikeSpam()) {
            $this->recordValidationErrors(new MessageBag([
                'form' => [__('digital-business-cards::messages.lead.submission_rejected')],
            ]));

            return;
        }

        try {
            $validated = $this->validate(
                $this->validationRules($card),
                ['website.prohibited' => __('digital-business-cards::messages.lead.submission_rejected')],
                $this->validationAttributes($card),
            );
        } catch (ValidationException $exception) {
            $this->recordValidationErrors($exception->validator->errors());

            return;
        }

        $this->validationErrors = [];
        $lead = $submission->submit(
            $request,
            $card,
            LeadFormData::attributes(
                $validated['fields'],
                (bool) ($validated['consent'] ?? false),
                $request->header('Referer'),
            ),
        );

        $this->submitted = true;
        $this->confirmationSent = (bool) $card->getAttribute('lead_send_confirmation')
            && filter_var($lead->getAttribute('email'), FILTER_VALIDATE_EMAIL) !== false;
        $this->reset('fields', 'consent');
        $this->formInitializedAt = now()->timestamp;
        $this->fields = array_fill_keys(
            array_column($card->validatableLeadFields(), 'key'),
            '',
        );

        $this->dispatch('contact-exchange-succeeded', modal: 'success-'.$this->getId());
    }

    public function render(): View
    {
        $card = $this->card();
        $fullName = $card->getAttribute('full_name')
            ?: $card->getAttribute('company_name')
            ?: __('digital-business-cards::messages.card.fallback_title');

        return view('digital-business-cards::livewire.contact-exchange-form', [
            'card' => $card,
            'fieldDefinitions' => $card->validatableLeadFields(),
            'fullName' => $fullName,
            'buttonClass' => match ($card->getAttribute('button_style')) {
                'pill' => 'card-button-pill',
                'square' => 'card-button-square',
                default => 'card-button-rounded',
            },
            'privacyUrl' => trim((string) $card->getAttribute('privacy_url')) ?: Config::privacyUrl(),
            'successModal' => 'success-'.$this->getId(),
            'errors' => (new ViewErrorBag)->put('default', new MessageBag($this->validationErrors)),
        ]);
    }

    /**
     * Return the component validation errors.
     *
     * Livewire 4.3 can seed a package component's error bag with null before
     * its initial Testbench render. Normalize that value through Livewire's
     * public API without depending on its internal component store.
     */
    public function getErrorBag(): MessageBag
    {
        $errorBag = parent::getErrorBag();

        if ($errorBag instanceof MessageBag) {
            return $errorBag;
        }

        $errorBag = new MessageBag;
        $this->setErrorBag($errorBag);

        return $errorBag;
    }

    /** @return array<string, array<int, string|\Closure>> */
    private function validationRules(DigitalBusinessCard $card): array
    {
        $rules = [];

        if (Config::get('spam_protection.enabled', true)) {
            $rules['website'] = ['prohibited'];
        }

        foreach (LeadFormData::rules($card) as $key => $fieldRules) {
            if ($key === 'consent' && ! $card->getAttribute('lead_consent_required') && $this->consent === null) {
                continue;
            }

            $rules[$key === 'consent' ? $key : 'fields.'.$key] = $fieldRules;
        }

        return $rules;
    }

    /** @return array<string, string> */
    private function validationAttributes(DigitalBusinessCard $card): array
    {
        $attributes = [];

        foreach (LeadFormData::validationAttributes($card) as $key => $label) {
            $attributes[$key === 'consent' ? $key : 'fields.'.$key] = $label;
        }

        return $attributes;
    }

    private function validateLiveAttribute(string $attribute): void
    {
        $card = $this->card();
        $rules = $this->validationRules($card);

        if (! array_key_exists($attribute, $rules)) {
            return;
        }

        try {
            $this->validateOnly(
                $attribute,
                $rules,
                ['website.prohibited' => __('digital-business-cards::messages.lead.submission_rejected')],
                $this->validationAttributes($card),
            );
            unset($this->validationErrors[$attribute]);
        } catch (ValidationException $exception) {
            $this->validationErrors[$attribute] = $exception->validator->errors()->get($attribute);
        }

        $this->setErrorBag(new MessageBag($this->validationErrors));
    }

    /** @param  array<int, string>  $fieldKeys */
    private function restoreLegacySubmission(array $fieldKeys): void
    {
        $oldInput = session()->getOldInput();

        foreach ($fieldKeys as $key) {
            if (array_key_exists($key, $oldInput)) {
                $value = $oldInput[$key];
                $this->fields[$key] = is_scalar($value) ? (string) $value : '';
            }
        }

        if (array_key_exists('consent', $oldInput)) {
            $this->consent = filter_var($oldInput['consent'], FILTER_VALIDATE_BOOL);
        }

        $errors = session('errors');

        if (! $errors instanceof ViewErrorBag) {
            return;
        }

        $legacyErrors = $errors->getBag('default');

        foreach ($fieldKeys as $key) {
            if ($legacyErrors->has($key)) {
                $this->validationErrors['fields.'.$key] = $legacyErrors->get($key);
            }
        }

        if ($legacyErrors->has('consent')) {
            $this->validationErrors['consent'] = $legacyErrors->get('consent');
        }
    }

    private function wasSubmittedTooQuickly(): bool
    {
        if (! Config::get('spam_protection.enabled', true)) {
            return false;
        }

        $minimumFillSeconds = max(0, (int) Config::get('spam_protection.minimum_fill_seconds', 2));

        return now()->timestamp < $this->formInitializedAt + $minimumFillSeconds;
    }

    private function looksLikeSpam(): bool
    {
        return Config::get('spam_protection.enabled', true)
            && ($this->website !== '' || $this->wasSubmittedTooQuickly());
    }

    private function recordValidationErrors(MessageBag $errors): void
    {
        $this->validationErrors = $errors->toArray();
        $this->setErrorBag($errors);
    }

    private function card(): DigitalBusinessCard
    {
        $card = $this->resolvePublishedCard($this->cardRouteKey);
        abort_unless($card->getAttribute('lead_form_enabled'), 404);

        return $card;
    }
}
