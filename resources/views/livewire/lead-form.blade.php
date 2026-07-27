@php
    use DigitalCardKit\Laravel\Support\Config;

    $fullName = $fullName ?? $card->full_name ?: $card->company_name ?: __('digital-business-cards::messages.card.fallback_title');
    $title = $card->lead_form_title ?: __('digital-business-cards::messages.lead.title');
    $description = $card->lead_form_description
        ?: __('digital-business-cards::messages.lead.description', ['name' => $fullName]);
    $headingId = $inline ? 'inline-lead-title' : 'exchange-title';
    $privacyUrl = trim((string) $card->privacy_url) ?: Config::privacyUrl();
@endphp

<h2 id="{{ $headingId }}">{{ $title }}</h2>
<p class="digital-card-exchange-copy">{{ $description }}</p>

@if ($submitted)
    <div class="digital-card-success-message">
        @if(session('card_confirmation_sent'))
            {{ __('digital-business-cards::messages.lead.success_confirmed', ['name' => $fullName]) }}
        @else
            {{ __('digital-business-cards::messages.lead.success_unconfirmed', ['name' => $fullName]) }}
        @endif
    </div>
@else
    <form wire:submit="submit" class="digital-card-form" @if($inline) data-inline-lead-form @endif>
        @foreach ($leadFields as $field)
            @php($key = (string) $field['key'])
            <label>
                <span>{{ $field['label'] }} @if($field['required'] ?? false)<b>*</b>@endif</span>
                @if(($field['type'] ?? 'text') === 'textarea')
                    <textarea 
                        name="{{ $key }}" 
                        rows="3" 
                        wire:model.live.debounce.300ms="leadData.{{ $key }}"
                        @required($field['required'] ?? false)
                    >{{ old($key) }}</textarea>
                @else
                    <input 
                        type="{{ $field['type'] ?? 'text' }}" 
                        name="{{ $key }}" 
                        wire:model.live.debounce.300ms="leadData.{{ $key }}"
                        @required($field['required'] ?? false)
                    >
                @endif
                @error("leadData.{$key}")<em>{{ $message }}</em>@enderror
            </label>
        @endforeach

        <label class="digital-card-consent">
            <input 
                type="checkbox" 
                wire:model="consent"
                @required($card->lead_consent_required)
            >
            <span>
                {{ __('digital-business-cards::messages.lead.consent') }}
                @if($privacyUrl !== '')
                    {!! __('digital-business-cards::messages.lead.consent_policy', [
                        'link' => '<a href="'.e($privacyUrl).'" target="_blank" rel="noopener">'
                            .e(__('digital-business-cards::messages.lead.privacy_policy')).'</a>',
                    ]) !!}
                @endif
            </span>
        </label>
        @error('consent')<em>{{ $message }}</em>@enderror

        <button 
            type="submit" 
            class="digital-card-submit {{ $buttonClass ?? '' }}"
            wire:loading.attr="disabled"
            wire:target="submit"
        >
            <span wire:loading.remove>{{ __('digital-business-cards::messages.actions.submit_lead') }}</span>
            <span wire:loading>{{ __('digital-business-cards::messages.actions.submitting') }}</span>
        </button>
    </form>
@endif