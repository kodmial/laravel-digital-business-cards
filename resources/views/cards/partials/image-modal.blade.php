@if ($card->avatar)
    <div
        class="digital-card-modal digital-card-image-modal"
        data-modal="image"
        x-cloak
        x-show="modal === 'image'"
        x-transition.opacity
        x-trap.inert.noscroll="modal === 'image'"
    >
        <div class="digital-card-modal-backdrop" x-on:click="close()"></div>
        <section class="digital-card-lightbox" role="dialog" aria-modal="true" aria-label="{{ $t('card.photo_of', ['name' => $fullName]) }}">
            <button type="button" class="digital-card-lightbox-close" x-on:click="close()" aria-label="{{ $t('actions.close_image') }}">×</button>
            <img src="{{ $card->storageUrl($card->avatar) }}" alt="{{ $fullName }}">
        </section>
    </div>
@endif
