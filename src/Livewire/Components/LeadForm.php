<?php

namespace DigitalCardKit\Laravel\Livewire\Components;

use DigitalCardKit\Laravel\Events\ContactExchangeCompleted;
use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardLead;
use DigitalCardKit\Laravel\Support\Config;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Livewire\Attributes\Locked;
use Livewire\Component;

class LeadForm extends Component
{
    #[Locked]
    public string $cardId;

    #[Locked]
    public bool $inline = false;

    #[Locked]
    public ?string $fullName = null;

    #[Locked]
    public ?string $buttonClass = null;

    public array $leadData = [];

    public bool $consent = false;

    public bool $submitted = false;

    public bool $sending = false;

    private const NATIVE_KEYS = ['name', 'phone', 'email', 'company', 'comment'];

    public function mount(string $cardId, bool $inline = false, ?string $fullName = null, ?string $buttonClass = null): void
    {
        $this->cardId = $cardId;
        $this->inline = $inline;
        $this->fullName = $fullName;
        $this->buttonClass = $buttonClass;
        $this->leadData = $this->initializeLeadData();
    }

    public function getCardProperty()
    {
        $cardModel = Config::model('card');
        return $cardModel::query()
            ->where('id', $this->cardId)
            ->where('is_published', true)
            ->firstOrFail();
    }

    public function getLeadFieldsProperty(): array
    {
        return $this->getCardProperty()->validatableLeadFields();
    }

    public function rules(): array
    {
        $card = $this->getCardProperty();
        $rules = [];
        $phoneUtil = PhoneNumberUtil::getInstance();

        foreach ($card->validatableLeadFields() as $field) {
            $fieldType = $field['type'] ?? 'text';
            $fieldRules = [
                ($field['required'] ?? false) ? 'required' : 'nullable',
                'string',
                'max:2000',
            ];

            if ($fieldType === 'email') {
                $fieldRules[] = 'email:rfc';
            }

            if ($fieldType === 'tel') {
                $fieldRules[] = static function (string $attribute, mixed $value, \Closure $fail) use ($phoneUtil): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }

                    $candidates = Config::phoneCandidateRegions();
                    foreach ($candidates as $region) {
                        try {
                            $proto = $phoneUtil->parse($value, $region);
                            if ($phoneUtil->isValidNumber($proto)) {
                                return;
                            }
                        } catch (NumberParseException) {
                            continue;
                        }
                    }

                    $proto = null;
                    try {
                        $proto = $phoneUtil->parse($value, null);
                    } catch (NumberParseException) {
                        $proto = null;
                    }

                    if ($proto !== null && $phoneUtil->isValidNumber($proto)) {
                        return;
                    }

                    $fail(__('validation.phone', ['attribute' => $attribute]));
                };
            }

            $rules[(string) $field['key']] = $fieldRules;
        }

        $rules['consent'] = ($card->lead_consent_required ?? false)
            ? ['required', 'accepted']
            : ['sometimes', 'accepted'];

        return $rules;
    }

    public function submit(): void
    {
        $this->sending = true;

        $validated = $this->validate();

        $lead = $this->createLead($validated);

        event(new ContactExchangeCompleted($lead->id));

        $this->submitted = true;
        $this->sending = false;

        Session::flash('card_lead_sent', true);
        Session::flash('card_confirmation_sent', Config::get('notifications.send_confirmation', false));

        // Dispatch event for Alpine.js to handle modal
        $this->dispatch('lead-submitted');
    }

    protected function createLead(array $validated)
    {
        $leadData = Arr::except($validated, ['consent']);

        $attributes = array_merge(
            array_combine(
                self::NATIVE_KEYS,
                array_map(static fn (string $key): mixed => $leadData[$key] ?? null, self::NATIVE_KEYS),
            ),
            [
                'custom_data' => Arr::except($leadData, self::NATIVE_KEYS),
                'consent_given' => (bool) $this->consent,
                'source' => Str::limit((string) request()->header('Referer'), 255, ''),
                'submitted_at' => now(),
            ],
        );

        $leadModel = Config::model('lead');
        $lead = $leadModel::query()->create($attributes);
        $card = $this->getCardProperty();
        $lead->card()->associate($card);
        $lead->save();

        return $lead;
    }

    protected function initializeLeadData(): array
    {
        $data = [];
        foreach ($this->getLeadFieldsProperty() as $field) {
            $key = (string) $field['key'];
            $data[$key] = '';
        }

        return $data;
    }

    public function render()
    {
        return view('digital-business-cards::livewire.lead-form')
            ->with('card', $this->getCardProperty());
    }
}
