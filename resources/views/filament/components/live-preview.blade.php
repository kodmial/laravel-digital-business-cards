@php
    /** @var \DigitalCardKit\Laravel\Models\DigitalBusinessCard|null $card */
    $card = $card ?? null;
    $url = $card?->publicUrl() ?? '';
    $isPublished = (bool) ($card?->is_published ?? false);
    $openLabel = __('digital-business-cards::admin.cards.preview.open');
    $notice = __('digital-business-cards::admin.cards.preview.unpublished');
@endphp

<div
    class="digital-card-live-preview"
    style="position: sticky; top: 1rem; display: grid; gap: .5rem; max-height: calc(100vh - 6rem);"
>
    <div style="display:flex; align-items:center; justify-content:space-between; gap:.5rem;">
        <span style="font-weight:600; font-size:.85rem;">
            {{ __('digital-business-cards::admin.cards.preview.heading') }}
        </span>
        @if($isPublished && $url !== '')
            <a href="{{ $url }}" target="_blank" rel="noopener"
               style="font-size:.75rem; font-weight:600; text-decoration:none;">
                {{ $openLabel }}
            </a>
        @endif
    </div>

    @if($isPublished && $url !== '')
        <iframe
            src="{{ $url }}"
            title="{{ __('digital-business-cards::admin.cards.preview.iframe_title') }}"
            loading="lazy"
            style="width:100%; height: 70vh; border: 1px solid rgb(229 231 235); border-radius: .5rem; background:#fff;"
            sandbox="allow-scripts allow-popups allow-downloads"
        ></iframe>
    @else
        <div style="
            border: 1px dashed rgb(209 213 219);
            border-radius: .5rem;
            padding: 1.5rem 1rem;
            text-align: center;
            color: rgb(107 114 128);
            font-size: .8rem;
            background: rgb(249 250 251);
        ">
            <p style="margin: 0 0 .5rem; font-weight:600;">
                {{ $notice }}
            </p>
            <p style="margin: 0;">
                {{ __('digital-business-cards::admin.cards.preview.unpublished_hint') }}
            </p>
        </div>
    @endif
</div>
