@if (session('card_lead_sent'))
    <div
        class="digital-card-modal"
        data-modal="legacy-success"
        x-cloak
        x-show="modal === 'legacy-success'"
        x-transition.opacity
        x-trap.inert.noscroll="modal === 'legacy-success'"
    >
        <div class="digital-card-modal-backdrop" x-on:click="close()"></div>
        <section class="digital-card-dialog digital-card-success-dialog" role="dialog" aria-modal="true" aria-labelledby="exchange-success-title" aria-describedby="exchange-success-description">
            <button type="button" class="digital-card-modal-close" x-on:click="close()" aria-label="{{ $t('actions.close') }}">×</button>
            <h2 id="exchange-success-title">{{ $t('lead.success_title') }}</h2>
            <p id="exchange-success-description">
                @if (session('card_confirmation_sent'))
                    {{ $t('lead.success_confirmed', ['name' => $fullName]) }}
                @else
                    {{ $t('lead.success_unconfirmed', ['name' => $fullName]) }}
                @endif
            </p>
            <button type="button" class="digital-card-submit card-button-pill" x-on:click="close()">{{ $t('actions.confirm') }}</button>
        </section>
    </div>
@endif
