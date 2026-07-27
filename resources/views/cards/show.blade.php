@php
    use DigitalCardKit\Laravel\Support\Config;
    use DigitalCardKit\Laravel\Support\ContactChannelRegistry;
    use DigitalCardKit\Laravel\Support\Css;
    $t = fn (string $key, array $replace = []): string => __('digital-business-cards::messages.'.$key, $replace);
    $fullName = $card->full_name ?: $card->company_name ?: $t('card.fallback_title');
    $cardUrl = $card->publicUrl();
    $buttonClass = match ($card->button_style) { 'pill' => 'card-button-pill', 'square' => 'card-button-square', default => 'card-button-rounded' };
    $fontClass = match ($card->font_family) { 'serif' => 'card-font-serif', 'mono' => 'card-font-mono', default => 'card-font-system' };
    $contacts = $card->publicContactMethods();
    $contactGroups = ContactChannelRegistry::group($contacts);
    $theme = $card->themeTokens();
    $aboutHeading = filled($card->first_name) || filled($card->last_name) || filled($card->middle_name)
        ? $t('card.about_person')
        : $t('card.about_company');
    $shouldOpenExchange = $card->lead_form_enabled && $errors->any();
    $routeName = fn (string $name): string => Config::routeName($name);
    $assetUrl = fn (string $file): string => Config::get('assets_url')
        ? asset(trim((string) Config::get('assets_url'), '/').'/'.$file)
        : route(Config::routeName('assets'), ['file' => $file]);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $card->meta_title ?: $fullName }}</title><meta name="description" content="{{ $card->meta_description ?: $card->headline }}">
    <meta property="og:title" content="{{ $card->meta_title ?: $fullName }}"><meta property="og:description" content="{{ $card->meta_description ?: $card->headline }}"><meta property="og:url" content="{{ $cardUrl }}">
    @if ($card->avatar)<meta property="og:image" content="{{ $card->storageUrl($card->avatar) }}">@endif
    <link rel="canonical" href="{{ $cardUrl }}">
    <link rel="stylesheet" href="{{ $assetUrl('card.css') }}">
    @if(config('digital-business-cards.use_livewire', false) && class_exists(\Livewire\Livewire::class))
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.0/dist/cdn.min.js" crossorigin="anonymous"></script>
        <script type="module" src="{{ $assetUrl('alpine.js') }}"></script>
    @endif
    <script type="module" src="{{ $assetUrl('card.js') }}"></script>
</head>
<body 
    class="digital-card-page {{ $fontClass }}{{ $theme['is_dark'] ? ' digital-card-page--dark' : ' digital-card-page--light' }}" 
    style="--card-bg: {{ $theme['background'] }}; --card-accent: {{ $theme['accent'] }}; --card-text: {{ $theme['text'] }}; --card-surface: {{ $theme['surface'] }}; --card-surface-muted: {{ $theme['surface_muted'] }}; --card-muted-text: {{ $theme['muted_text'] }}; --card-border: {{ $theme['border'] }}; --card-page-bg: {{ $theme['page_background'] }}; --card-shadow: {{ $theme['shadow'] }}; --card-accent-rgb: {{ $theme['accent_rgb'] }};"
    @if(config('digital-business-cards.use_livewire', false) && class_exists(\Livewire\Livewire::class))
        x-data="{ 
            openModal: null, 
            returnFocusTo: null,
            init() {
                // Auto-open success modal if present
                if (document.querySelector('[data-modal="success"]')) {
                    this.openModal = 'success';
                }
            }
        }"
        x-init="
            $watch('openModal', (value) => {
                if (value) {
                    document.body.classList.add('digital-card-modal-open');
                    $nextTick(() => {
                        const dialog = document.querySelector(`[data-modal=\"${value}\"]`);
                        dialog?.querySelector('button, input, textarea, select, a[href], [tabindex]:not([tabindex=\"-1\"])')?.focus();
                    });
                } else {
                    document.body.classList.remove('digital-card-modal-open');
                    returnFocusTo?.focus();
                }
            });
        "
        @keydown.escape.window="openModal = null"
        @keydown.tab.window="
            if (openModal) {
                const visibleModal = document.querySelector('[data-modal]:not([hidden])');
                const focusable = [...visibleModal?.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex=\"-1\"])') || []].filter(el => !el.hidden);
                if (focusable.length === 0) return;
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if ($event.shiftKey && document.activeElement === first) {
                    $event.preventDefault();
                    last.focus();
                } else if (!$event.shiftKey && document.activeElement === last) {
                    $event.preventDefault();
                    first.focus();
                }
            }
        "
        @lead-submitted.window="openModal = 'success'"
    @endif
>
<main class="digital-card-shell" data-digital-card data-events-url="{{ route($routeName('events.store'), $card) }}">
    <section class="digital-card-hero" @if($card->cover_image) style="background-image:linear-gradient(180deg,rgba(8,13,26,.15),var(--card-bg) 94%),{{ Css::url((string) $card->storageUrl($card->cover_image)) }}" @endif>
        @if ($card->logo)
            <div class="digital-card-topbar">
                <a href="{{ $card->publicUrl() }}" aria-label="{{ $card->company_name ?: $fullName }}">
                    <img src="{{ $card->storageUrl($card->logo) }}" alt="{{ $card->company_name ?: $t('card.logo') }}" class="digital-card-logo">
                </a>
            </div>
        @endif
        <div class="digital-card-profile">
            @if ($card->avatar)
                <button type="button" class="digital-card-avatar-button" @if(config('digital-business-cards.use_livewire', false) && class_exists(\Livewire\Livewire::class)) @click="openModal = 'image'; returnFocusTo = $el" @else data-open-image @endif aria-label="{{ $t('card.open_photo', ['name' => $fullName]) }}">
                    <img src="{{ $card->storageUrl($card->avatar) }}" alt="{{ $fullName }}" class="digital-card-avatar">
                </button>
            @else
                <div class="digital-card-avatar digital-card-avatar-placeholder">{{ mb_strtoupper(mb_substr($fullName, 0, 1)) }}</div>
            @endif
            <h1>{{ $fullName }}</h1>@if ($card->job_title)<p class="digital-card-title">{{ $card->job_title }}</p>@endif @if ($card->company_name)<p class="digital-card-company">{{ $card->company_name }}</p>@endif @if ($card->headline)<p class="digital-card-headline">{{ $card->headline }}</p>@endif
        </div>
        <div class="digital-card-primary-actions">
            <button type="button" class="digital-card-save {{ $buttonClass }}" @if(config('digital-business-cards.use_livewire', false) && class_exists(\Livewire\Livewire::class)) @click="openModal = 'save'; returnFocusTo = $el" @else data-save-contact @endif data-track="vcard"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/></svg>{{ $t('actions.save_contact') }}</button>
        </div>
    </section>

    <div class="digital-card-content">
        @if ($contacts)
            <section class="digital-card-section digital-card-contacts-section" aria-label="{{ $t('card.contacts_label') }}">
                <div class="digital-card-contact-list">
                @foreach ($contactGroups as $group)
                    @if ($group['type'] === 'social')
                        @php
                            $socialCount = count($group['items']);
                        @endphp
                        <div class="digital-card-social-row digital-card-social-row--{{ $socialCount === 1 ? 'single' : 'multiple' }}" style="--social-count: {{ $socialCount }}" aria-label="{{ $t('card.social_label') }}">
                        @foreach ($group['items'] as $contact)
                        @php
                            $type = (string) ($contact['type'] ?? 'link');
                            $label = ContactChannelRegistry::label($contact);
                        @endphp
                        <a href="{{ ContactChannelRegistry::href($contact) }}" class="digital-card-contact digital-card-contact--social digital-card-contact--{{ $type }}" target="_blank" rel="noopener noreferrer">
                            <span class="digital-card-contact-icon"><x-digital-business-cards::contact-icon :type="$type" /></span>
                            <span class="digital-card-contact-copy"><strong>{{ $label }}</strong></span>
                        </a>
                        @endforeach
                        </div>
                    @else
                        @php
                            $contact = $group['items'][0];
                            $type = (string) ($contact['type'] ?? 'link');
                            $label = ContactChannelRegistry::label($contact);
                            $value = ContactChannelRegistry::displayValue($contact);
                            $href = ContactChannelRegistry::href($contact);
                            $visibleLabel = $type === 'website' && $value !== '' ? $value : $label;
                            $visibleValue = $type === 'website' ? '' : $value;
                        @endphp
                        <a href="{{ $href }}" class="digital-card-contact digital-card-contact--{{ $type }}" aria-label="{{ $type === 'website' ? $label.': '.$href : $label }}" @if(in_array($type, ['website', 'link', 'telegram', 'max', 'whatsapp'], true)) target="_blank" rel="noopener noreferrer" @endif>
                            <span class="digital-card-contact-icon"><x-digital-business-cards::contact-icon :type="$type" /></span>
                            <span class="digital-card-contact-copy"><strong>{{ $visibleLabel }}</strong>@if ($visibleValue)<small>{{ $visibleValue }}</small>@endif</span>
                            <span class="digital-card-chevron" aria-hidden="true">›</span>
                        </a>
                    @endif
                @endforeach
                </div>
            </section>
        @endif
        @if ($card->company_name || $card->logo)<section class="digital-card-section digital-card-company-section"><h2>{{ $t('card.company_heading') }}</h2><div class="digital-card-company-card">@if($card->logo)<img src="{{ $card->storageUrl($card->logo) }}" alt="{{ $card->company_name ?: $t('card.logo') }}" class="digital-card-company-logo">@endif @if($card->company_name)<strong>{{ $card->company_name }}</strong>@endif</div></section>@endif
        @if ($card->about)<section class="digital-card-section"><h2>{{ $aboutHeading }}</h2><div class="digital-card-copy">{!! nl2br(e($card->about)) !!}</div></section>@endif
        @if ($card->blocks->isNotEmpty())<section class="digital-card-section"><h2>{{ $t('card.materials') }}</h2><div class="digital-card-blocks">@foreach ($card->blocks as $block)
            @php($data = $block->data ?: []) @php($media = $card->storageUrl($data['media'] ?? null)) @php($target = ($data['open_in_new_tab'] ?? true) ? '_blank' : '_self')
            @if (in_array($block->type, ['link', 'social']))<a href="{{ $block->url }}" target="{{ $target }}" @if($target === '_blank') rel="noopener noreferrer" @endif class="digital-card-link-block {{ $buttonClass }}" data-track="cta" data-block-id="{{ $block->id }}"><span><strong>{{ $block->button_label ?: $block->title ?: $t('actions.open') }}</strong>@if($block->content)<small>{{ $block->content }}</small>@endif</span><span>›</span></a>
            @elseif ($block->type === 'text' && ($block->title || $block->content))<article class="digital-card-copy-block"><h3>{{ $block->title }}</h3><div>{!! nl2br(e($block->content)) !!}</div></article>
            @elseif ($block->type === 'gallery')<article class="digital-card-gallery-block">@if($block->title)<h3>{{ $block->title }}</h3>@endif @if($block->content)<p>{{ $block->content }}</p>@endif<div class="digital-card-gallery">@foreach (($data['gallery'] ?? []) as $image)<a href="{{ $card->storageUrl($image) }}" target="_blank" rel="noopener"><img src="{{ $card->storageUrl($image) }}" alt="{{ $block->title ?: $t('card.gallery_image', ['number' => $loop->iteration]) }}" loading="lazy"></a>@endforeach</div></article>
            @elseif ($block->type === 'video')<article class="digital-card-media-block">@if($block->title)<h3>{{ $block->title }}</h3>@endif @if($block->content)<p>{{ $block->content }}</p>@endif @if($block->url)<a href="{{ $block->url }}" target="{{ $target }}" @if($target === '_blank') rel="noopener noreferrer" @endif class="digital-card-video-link" data-track="video" data-block-id="{{ $block->id }}">▶ {{ $t('actions.watch_video') }}</a>@endif</article>
            @elseif ($block->type === 'file')<a href="{{ $media ?: $block->url }}" target="_blank" rel="noopener noreferrer" class="digital-card-file-block" data-track="file" data-block-id="{{ $block->id }}"><span>⇩</span><span><strong>{{ $block->title ?: $t('actions.download_file') }}</strong>@if($block->content)<small>{{ $block->content }}</small>@endif</span></a>
            @elseif ($block->type === 'banner')<a href="{{ $block->url }}" target="{{ $target }}" @if($target === '_blank') rel="noopener noreferrer" @endif class="digital-card-banner" data-track="cta" data-block-id="{{ $block->id }}">@if($media)<img src="{{ $media }}" alt="{{ $block->title }}" loading="lazy">@endif<span><strong>{{ $block->title }}</strong>@if($block->content)<small>{{ $block->content }}</small>@endif</span></a>
            @endif
        @endforeach</div></section>@endif
        @if ($card->lead_form_enabled)
            <section class="digital-card-section digital-card-inline-lead-section" aria-labelledby="inline-lead-title">
                <div class="digital-card-inline-lead">
                    @if(config('digital-business-cards.use_livewire', false) && class_exists(\Livewire\Livewire::class))
                        <livewire:lead-form :card-id="$card->id" :full-name="$fullName" :button-class="$buttonClass" :inline="true" />
                    @else
                        <x-digital-business-cards::lead-form :card="$card" :full-name="$fullName" :button-class="$buttonClass" inline />
                    @endif
                </div>
            </section>
        @endif
    </div>
    <footer class="digital-card-footer"><span>{{ $t('card.footer') }}</span></footer>
</main>
@if ($card->avatar)
<div class="digital-card-modal digital-card-image-modal" data-modal="image" @if(config('digital-business-cards.use_livewire', false) && class_exists(\Livewire\Livewire::class)) x-show="openModal === 'image'" @else hidden @endif>
    <div class="digital-card-modal-backdrop" @if(config('digital-business-cards.use_livewire', false) && class_exists(\Livewire\Livewire::class)) @click="openModal = null" @else data-close-modal @endif></div>
    <section class="digital-card-lightbox" role="dialog" aria-modal="true" aria-label="{{ $t('card.photo_of', ['name' => $fullName]) }}">
        <button type="button" class="digital-card-lightbox-close" @if(config('digital-business-cards.use_livewire', false) && class_exists(\Livewire\Livewire::class)) @click="openModal = null" @else data-close-modal @endif aria-label="{{ $t('actions.close_image') }}">×</button>
        <img src="{{ $card->storageUrl($card->avatar) }}" alt="{{ $fullName }}">
    </section>
</div>
@endif
<div class="digital-card-modal" data-modal="save" @if(config('digital-business-cards.use_livewire', false) && class_exists(\Livewire\Livewire::class)) x-show="openModal === 'save'" @else hidden @endif><div class="digital-card-modal-backdrop" @if(config('digital-business-cards.use_livewire', false) && class_exists(\Livewire\Livewire::class)) @click="openModal = null" @else data-close-modal @endif></div><section class="digital-card-dialog digital-card-save-dialog" role="dialog" aria-modal="true" aria-labelledby="save-title"><button type="button" class="digital-card-modal-close" @if(config('digital-business-cards.use_livewire', false) && class_exists(\Livewire\Livewire::class)) @click="openModal = null" @else data-close-modal @endif aria-label="{{ $t('actions.close') }}">×</button><h2 id="save-title">{{ $t('card.delivery_title') }}</h2>@foreach ($contacts as $contact) @php($type = $contact['type'] ?? '') @if(in_array($type, ['telegram', 'max'], true))<a href="{{ ContactChannelRegistry::href($contact) }}" target="_blank" rel="noopener noreferrer" class="digital-card-delivery"><x-digital-business-cards::contact-icon :type="$type" /><span>{{ $t('actions.send_via', ['channel' => $type === 'max' ? 'MAX' : 'Telegram']) }}</span></a>@endif @endforeach<a href="{{ route($routeName('download'), $card) }}" data-download-vcard class="digital-card-save digital-card-save-dialog-vcard {{ $buttonClass }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/></svg><span>{{ $t('actions.save_contact') }}</span></a>@if ($card->lead_form_enabled)<button type="button" @if(config('digital-business-cards.use_livewire', false) && class_exists(\Livewire\Livewire::class)) @click="openModal = 'exchange'" @else data-open-exchange @endif data-track="contact" class="digital-card-exchange {{ $buttonClass }}">{{ $t('actions.exchange') }}</button>@endif</section></div>
@if ($card->lead_form_enabled)<div class="digital-card-modal" data-modal="exchange" @if(config('digital-business-cards.use_livewire', false) && class_exists(\Livewire\Livewire::class)) x-show="openModal === 'exchange' || ($errors->any() && openModal === null)" x-init="$el._x_hide = false" @else @unless($shouldOpenExchange) hidden @endunless @endif><div class="digital-card-modal-backdrop" @if(config('digital-business-cards.use_livewire', false) && class_exists(\Livewire\Livewire::class)) @click="openModal = null" @else data-close-modal @endif></div><section class="digital-card-dialog" role="dialog" aria-modal="true" aria-labelledby="exchange-title"><button type="button" class="digital-card-modal-close" @if(config('digital-business-cards.use_livewire', false) && class_exists(\Livewire\Livewire::class)) @click="openModal = null" @else data-close-modal @endif aria-label="{{ $t('actions.close') }}">×</button>
    @if(config('digital-business-cards.use_livewire', false) && class_exists(\Livewire\Livewire::class))
        <livewire:lead-form :card-id="$card->id" :full-name="$fullName" :button-class="$buttonClass" />
    @else
        <x-digital-business-cards::lead-form :card="$card" :full-name="$fullName" :button-class="$buttonClass" />
    @endif
</section></div>@endif
@if (session('card_lead_sent'))
    <div class="digital-card-modal" data-modal="success" @if(config('digital-business-cards.use_livewire', false) && class_exists(\Livewire\Livewire::class)) x-show="openModal === 'success'" x-init="$el._x_hide = false; openModal = 'success'" @endif>
        <div class="digital-card-modal-backdrop" @if(config('digital-business-cards.use_livewire', false) && class_exists(\Livewire\Livewire::class)) @click="openModal = null" @else data-close-modal @endif></div>
        <section class="digital-card-dialog digital-card-success-dialog" role="dialog" aria-modal="true" aria-labelledby="exchange-success-title" aria-describedby="exchange-success-description">
            <button type="button" class="digital-card-modal-close" @if(config('digital-business-cards.use_livewire', false) && class_exists(\Livewire\Livewire::class)) @click="openModal = null" @else data-close-modal @endif aria-label="{{ $t('actions.close') }}">×</button>
            <h2 id="exchange-success-title">{{ $t('lead.success_title') }}</h2>
            <p id="exchange-success-description">
                @if(session('card_confirmation_sent'))
                    {{ $t('lead.success_confirmed', ['name' => $fullName]) }}
                @else
                    {{ $t('lead.success_unconfirmed', ['name' => $fullName]) }}
                @endif
            </p>
            <button type="button" class="digital-card-submit card-button-pill" @if(config('digital-business-cards.use_livewire', false) && class_exists(\Livewire\Livewire::class)) @click="openModal = null" @else data-close-modal @endif>{{ $t('actions.confirm') }}</button>
        </section>
    </div>
@endif
</body></html>
