<div @if($inline) class="digital-card-livewire-form" @else class="digital-card-livewire-modals" @endif>
    @if($inline)
        @include('digital-business-cards::livewire.partials.contact-exchange-fields')
    @else
        <div
            class="digital-card-modal"
            data-modal="exchange"
            x-cloak
            x-show="modal === 'exchange'"
            x-transition.opacity
            x-trap.inert.noscroll="modal === 'exchange'"
        >
            <div class="digital-card-modal-backdrop" x-on:click="close()"></div>
            <section class="digital-card-dialog" role="dialog" aria-modal="true" aria-labelledby="exchange-title">
                <button type="button" class="digital-card-modal-close" x-on:click="close()" aria-label="{{ __('digital-business-cards::messages.actions.close') }}">×</button>
                @include('digital-business-cards::livewire.partials.contact-exchange-fields')
            </section>
        </div>
    @endif

    <div
        class="digital-card-modal"
        data-modal="{{ $successModal }}"
        x-cloak
        x-show="modal === @js($successModal)"
        x-transition.opacity
        x-trap.inert.noscroll="modal === @js($successModal)"
    >
        <div class="digital-card-modal-backdrop" x-on:click="close()"></div>
        <section class="digital-card-dialog digital-card-success-dialog" role="dialog" aria-modal="true" aria-labelledby="{{ $successModal }}-title" aria-describedby="{{ $successModal }}-description">
            <button type="button" class="digital-card-modal-close" x-on:click="close()" aria-label="{{ __('digital-business-cards::messages.actions.close') }}">×</button>
            <h2 id="{{ $successModal }}-title">{{ __('digital-business-cards::messages.lead.success_title') }}</h2>
            <p id="{{ $successModal }}-description">
                @if($confirmationSent)
                    {{ __('digital-business-cards::messages.lead.success_confirmed', ['name' => $fullName]) }}
                @else
                    {{ __('digital-business-cards::messages.lead.success_unconfirmed', ['name' => $fullName]) }}
                @endif
            </p>
            <button type="button" class="digital-card-submit card-button-pill" x-on:click="close()">{{ __('digital-business-cards::messages.actions.confirm') }}</button>
        </section>
    </div>
</div>
