<?php

namespace DigitalCardKit\Laravel\Livewire\Components;

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardLead;
use DigitalCardKit\Laravel\Support\Config;
use DigitalCardKit\Laravel\Support\RateLimits;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Throwable;

/**
 * Livewire component for lead form submission
 * 
 * Provides reactive form handling with real-time validation
 * and loading states when use_livewire is enabled.
 * 
 * @property-read string $cardId The card slug identifier
 * @property-read string $fullName The display name for the card
 * @property-read string $buttonClass CSS classes for buttons
 * @property-read array $leadData Form field data
 * @property-read bool $consent Consent checkbox state
 * @property-read bool $submitted Form submission state
 * @property-read bool $sending Form sending state
 */
class LeadForm extends Component
{
    public string $cardId = '';
    public string $fullName = '';
    public string $buttonClass = '';
    public array $leadData = [];
    public bool $consent = false;
    public bool $submitted = false;
    public bool $sending = false;

    /**
     * Mount the component with card data.
     * 
     * @param string $cardId The card slug identifier
     * @param string $fullName The display name for the card
     * @param string $buttonClass CSS classes for buttons
     */
    public function mount(string $cardId, string $fullName, string $buttonClass): void
    {
        $this->cardId = $cardId;
        $this->fullName = $fullName;
        $this->buttonClass = $buttonClass;
        
        // Initialize lead data fields
        $card = $this->getCardProperty();
        foreach ($card->leadFields() as $field) {
            $key = (string) $field['key'];
            $this->leadData[$key] = '';
        }
    }

    /**
     * Get the card model instance.
     * 
     * @return DigitalBusinessCard The published card instance
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    private function getCardProperty(): DigitalBusinessCard
    {
        $cardModel = Config::model('card');
        
        /** @var DigitalBusinessCard */
        return $cardModel::query()
            ->where('slug', $this->cardId)
            ->where('is_published', true)
            ->firstOrFail();
    }

    /**
     * Get the lead fields from the card.
     * 
     * @return array Lead field definitions
     */
    public function getLeadFieldsProperty(): array
    {
        return $this->getCardProperty()->leadFields();
    }

    /**
     * Get validation rules for the form.
     * 
     * @return array<string, mixed[]> Validation rules keyed by field name
     */
    public function rules(): array
    {
        $card = $this->getCardProperty();
        $rules = [];

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
                $fieldRules[] = 'regex:/^[\d\s\-\+\(\)]+$/';
            }

            $rules[(string) $field['key']] = $fieldRules;
        }

        $rules['consent'] = ($card->lead_consent_required ?? false)
            ? ['required', 'accepted']
            : ['sometimes', 'nullable', 'boolean'];

        return $rules;
    }

    /**
     * Submit the lead form.
     */
    public function submit(): void
    {
        $this->sending = true;

        // Rate limiting
        $key = RateLimits::LEADS.'|'.$this->cardId.'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $this->addError('error', __('Too many attempts. Please try again later.'));
            $this->sending = false;
            return;
        }

        RateLimiter::hit($key, 60);

        $validated = $this->validate();

        $lead = $this->createLead($validated);

        $this->submitted = true;
        $this->sending = false;

        // Dispatch event for Alpine.js to handle
        $this->dispatch('lead-submitted');
    }

    /**
     * Create the lead record.
     * 
     * @param array $validated Validated form data
     * @return DigitalBusinessCardLead The created lead instance
     */
    private function createLead(array $validated): DigitalBusinessCardLead
    {
        $card = $this->getCardProperty();
        $leadModel = Config::model('lead');

        $leadData = [
            'digital_business_card_id' => $card->id,
            'consent_given' => (bool) $this->consent,
            'source' => 'livewire-form',
        ];

        // Add dynamic field data
        foreach ($card->leadFields() as $field) {
            $key = (string) $field['key'];
            /** @var DigitalBusinessCardLead */
            $leadModelInstance = new $leadModel();
            $fillable = $leadModelInstance->getFillable();
            if (in_array($key, $fillable, true)) {
                $leadData[$key] = $validated[$key] ?? null;
            } else {
                $leadData['custom_data'][$key] = $validated[$key] ?? null;
            }
        }

        /** @var DigitalBusinessCardLead */
        return $leadModel::query()->create($leadData);
    }

    /**
     * Render the component.
     * 
     * @return View The rendered view
     */
    public function render(): View
    {
        $card = $this->getCardProperty();
        
        return view('digital-business-cards::livewire.lead-form', [
            'card' => $card,
            'fullName' => $this->fullName,
            'buttonClass' => $this->buttonClass,
        ]);
    }
}
