<?php

namespace DigitalCardKit\Laravel\Livewire;

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Services\LeadSubmission;
use DigitalCardKit\Laravel\Support\Config;
use DigitalCardKit\Laravel\Support\LeadFormData;
use DigitalCardKit\Laravel\Support\RateLimits;
use DigitalCardKit\Laravel\Support\ResolvesModels;
use Illuminate\Contracts\View\View;
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

        $this->cardRouteKey = (string) $card->getRouteKey();
        $this->inline = $inline;
        $this->formInitializedAt = now()->timestamp;
        $this->fields = array_fill_keys(
            array_column($card->validatableLeadFields(), 'key'),
            '',
        );
    }

    public function submit(LeadSubmission $submission): void
    {
        $card = $this->card();
        RateLimits::ensureLeadSubmissionIsAllowed(request(), (string) $card->getRouteKey());

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
            );
        } catch (ValidationException $exception) {
            $this->recordValidationErrors($exception->validator->errors());

            return;
        }

        $this->validationErrors = [];
        $lead = $submission->submit(
            request(),
            $card,
            LeadFormData::attributes(
                $validated['fields'],
                (bool) ($validated['consent'] ?? false),
                request()->header('Referer'),
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
