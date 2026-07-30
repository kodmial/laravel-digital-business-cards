<div
    class="digital-card-modal"
    data-modal="save"
    x-cloak
    x-show="modal === 'save'"
    x-transition.opacity
    x-trap.inert.noscroll="modal === 'save'"
>
    <div class="digital-card-modal-backdrop" x-on:click="close()"></div>
    <section class="digital-card-dialog digital-card-save-dialog" role="dialog" aria-modal="true" aria-labelledby="save-title">
        <button type="button" class="digital-card-modal-close" x-on:click="close()" aria-label="{{ $t('actions.close') }}">×</button>
        <h2 id="save-title">{{ $t('card.delivery_title') }}</h2>

        @foreach ($contacts as $contact)
            @php($type = $contact['type'] ?? '')
            @if (in_array($type, ['telegram', 'max'], true))
                <a href="{{ \DigitalCardKit\Laravel\Support\ContactChannelRegistry::href($contact) }}" target="_blank" rel="noopener noreferrer" class="digital-card-delivery">
                    <x-digital-business-cards::contact-icon :type="$type" />
                    <span>{{ $t('actions.send_via', ['channel' => $type === 'max' ? 'MAX' : 'Telegram']) }}</span>
                </a>
            @endif
        @endforeach

        {{-- Native downloads expose no reliable completion event; this delay preserves the existing follow-up prompt. --}}
        <a
            href="{{ route($routeName('download'), $card) }}"
            data-download-vcard
            x-on:click="if (!$event.defaultPrevented) { window.setTimeout(() => open('exchange'), exchangePromptDelayAfterDownload) }"
            class="digital-card-save digital-card-save-dialog-vcard {{ $buttonClass }}"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/></svg>
            <span>{{ $t('actions.save_contact') }}</span>
        </a>

        @if ($card->lead_form_enabled)
            <button type="button" x-on:click="open('exchange')" data-track="contact" class="digital-card-exchange {{ $buttonClass }}">{{ $t('actions.exchange') }}</button>
        @endif
    </section>
</div>
