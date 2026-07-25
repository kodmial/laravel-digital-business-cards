@props([
    'card',
    'fullName',
    'buttonClass',
    'inline' => false,
])


@php
    $title = $card->lead_form_title ?: 'Оставьте свои контакты для связи';
    $description = $card->lead_form_description ?: "Оставьте контакты, чтобы {$fullName} мог(ла) связаться с вами.";
    $headingId = $inline ? 'inline-lead-title' : 'exchange-title';
    $privacyUrl = trim((string) ($card->privacy_url ?: config('digital-business-cards.privacy_url')));
@endphp

<h2 id="{{ $headingId }}">{{ $title }}</h2>
<p class="digital-card-exchange-copy">{{ $description }}</p>
<form action="{{ route(config('digital-business-cards.route_name_prefix', 'cards.').'leads.store', $card) }}" method="post" class="digital-card-form" @if($inline) data-inline-lead-form @endif>
    @csrf
    @foreach ($card->leadFields() as $field)
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
            Согласен(на) на обработку персональных данных
            @if($privacyUrl !== '')
                согласно <a href="{{ $privacyUrl }}" target="_blank" rel="noopener">политике конфиденциальности</a>
            @endif
        </span>
    </label>
    @error('consent')<em>{{ $message }}</em>@enderror
    <button type="submit" class="digital-card-submit {{ $buttonClass }}">Поделиться контактом</button>
</form>
