<?php

namespace DigitalCardKit\Laravel\Livewire;

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Services\LeadSubmission;
use DigitalCardKit\Laravel\Support\Config;
use DigitalCardKit\Laravel\Support\LeadFormData;
use DigitalCardKit\Laravel\Support\RateLimits;
use DigitalCardKit\Laravel\Support\ResolvesModels;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Livewire\Attributes\Locked;
use Livewire\Component;

use function Livewire\store;

class ContactExchangeForm extends Component
{
    use ResolvesModels;

    /** @var array<string, mixed> */
    public array $fields = [];

    public bool $consent = false;

    public bool $submitted = false;

    public bool $confirmationSent = false;

    /** @var array<string, array<int, string>> */
    public array $validationErrors = [];

    #[Locked]
    public string $cardRouteKey;

    #[Locked]
    public bool $inline = false;

    public function mount(DigitalBusinessCard $card, bool $inline = false): void
    {
        abort_unless($card->getAttribute('lead_form_enabled'), 404);

        $this->cardRouteKey = (string) $card->getRouteKey();
        $this->inline = $inline;
        $this->fields = array_fill_keys(
            array_column($card->validatableLeadFields(), 'key'),
            '',
        );
    }

    public function submit(LeadSubmission $submission): void
    {
        $card = $this->card();
        RateLimits::ensureLeadSubmissionIsAllowed(request(), (string) $card->getRouteKey());

        $validationData = ['fields' => $this->fields];
        if ($card->getAttribute('lead_consent_required') || $this->consent) {
            $validationData['consent'] = $this->consent;
        }

        $validator = Validator::make(
            $validationData,
            $this->rules(),
        );

        if ($validator->fails()) {
            $this->validationErrors = $validator->errors()->toArray();
            $this->setErrorBag($validator->errors());

            return;
        }

        $this->validationErrors = [];
        $this->resetErrorBag();
        $validated = $validator->validated();
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
        ]);
    }

    public function getErrorBag(): MessageBag
    {
        $errorBag = store($this)->get('errorBag');

        if ($errorBag instanceof MessageBag) {
            return $errorBag;
        }

        $errorBag = new MessageBag;
        store($this)->set('errorBag', $errorBag);

        return $errorBag;
    }

    public function setErrorBag($bag): MessageBag
    {
        $errorBag = $bag instanceof MessageBag ? $bag : new MessageBag($bag);
        store($this)->set('errorBag', $errorBag);

        return $errorBag;
    }

    /** @return array<string, array<int, string|\Closure>> */
    protected function rules(): array
    {
        $rules = [];

        foreach (LeadFormData::rules($this->card()) as $key => $fieldRules) {
            $rules[$key === 'consent' ? $key : 'fields.'.$key] = $fieldRules;
        }

        return $rules;
    }

    private function card(): DigitalBusinessCard
    {
        $card = $this->resolvePublishedCard($this->cardRouteKey);
        abort_unless($card->getAttribute('lead_form_enabled'), 404);

        return $card;
    }
}
