@php
    $title = $card->lead_form_title ?: __('digital-business-cards::messages.lead.title');
    $description = $card->lead_form_description
        ?: __('digital-business-cards::messages.lead.description', ['name' => $fullName]);
    $headingId = $inline ? 'inline-lead-title' : 'exchange-title';
@endphp

<h2 id="{{ $headingId }}">{{ $title }}</h2>
<p class="digital-card-exchange-copy">{{ $description }}</p>
<form wire:submit="submit" class="digital-card-form" novalidate @if($inline) data-inline-lead-form @endif>
    <label class="digital-card-honeypot" aria-hidden="true" inert>
        <span>Website</span>
        <input wire:model="website" type="text" name="website" tabindex="-1" autocomplete="off">
    </label>
    @foreach ($fieldDefinitions as $field)
        @php($key = $field['key'])
        <label wire:key="lead-field-{{ $key }}">
            <span>{{ $field['label'] }} @if($field['required'] ?? false)<b>*</b>@endif</span>
            @if(($field['type'] ?? 'text') === 'textarea')
                <textarea wire:model="fields.{{ $key }}" wire:blur="validateField('{{ $key }}')" name="{{ $key }}" rows="3" @required($field['required'] ?? false)></textarea>
            @else
                <input wire:model="fields.{{ $key }}" wire:blur="validateField('{{ $key }}')" type="{{ $field['type'] ?? 'text' }}" name="{{ $key }}" @required($field['required'] ?? false)>
            @endif
            @error('fields.'.$key)
                <em>{{ $message }}</em>
            @enderror
        </label>
    @endforeach
    <label class="digital-card-consent">
        <input wire:model="consent" wire:change="validateConsent" wire:blur="validateConsent" type="checkbox" name="consent" value="1" @required($card->lead_consent_required)>
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
    @error('consent')
        <em>{{ $message }}</em>
    @enderror
    @error('form')
        <em>{{ $message }}</em>
    @enderror
    <button type="submit" class="digital-card-submit {{ $buttonClass }}" wire:loading.attr="disabled" wire:target="submit">
        <span wire:loading.remove wire:target="submit">{{ __('digital-business-cards::messages.actions.submit_lead') }}</span>
        <span wire:loading wire:target="submit">{{ __('digital-business-cards::messages.actions.submitting_lead') }}</span>
    </button>
</form>
