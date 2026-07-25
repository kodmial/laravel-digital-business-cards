@props([
    'card',
    'fullName',
    'buttonClass',
    'inline' => false,
])


@php
    use DigitalCardKit\Laravel\Support\Config;

    $title = $card->lead_form_title ?: __('digital-business-cards::messages.lead.title');
    $description = $card->lead_form_description
        ?: __('digital-business-cards::messages.lead.description', ['name' => $fullName]);
    $headingId = $inline ? 'inline-lead-title' : 'exchange-title';
    $privacyUrl = trim((string) $card->privacy_url) ?: Config::privacyUrl();
@endphp

<h2 id="{{ $headingId }}">{{ $title }}</h2>
<p class="digital-card-exchange-copy">{{ $description }}</p>
<form action="{{ route(Config::routeName('leads.store'), $card) }}" method="post" class="digital-card-form" @if($inline) data-inline-lead-form @endif>
    @csrf
    @foreach ($card->validatableLeadFields() as $field)
        @php($key = $field['key'])
        <label>
            <span>{{ $field['label'] }} @if($field['required'] ?? false)<b>*</b>@endif</span>
            @if(($field['type'] ?? 'text') === 'textarea')
                <textarea name="{{ $key }}" rows="3" @required($field['required'] ?? false)>{{ old($key) }}</textarea>
            @else
                <input type="{{ $field['type'] ?? 'text' }}" name="{{ $key }}" value="{{ old($key) }}" @required($field['required'] ?? false)>
            @endif
            @error($key)<em>{{ $message }}</em>@enderror
        </label>
    @endforeach
    <label class="digital-card-consent">
        <input type="checkbox" name="consent" value="1" @checked(old('consent')) @required($card->lead_consent_required)>
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
    <button type="submit" class="digital-card-submit {{ $buttonClass }}">{{ __('digital-business-cards::messages.actions.submit_lead') }}</button>
</form>
