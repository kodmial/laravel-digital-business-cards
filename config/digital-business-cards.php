<?php

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardBlock;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardEvent;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardLead;
use DigitalCardKit\Laravel\Support\RateLimits;
use Filament\Http\Middleware\Authenticate;

return [
    'route_prefix' => 'card',
    'route_name_prefix' => 'cards.',
    'route_middleware' => ['web'],
    'asset_route_prefix' => 'digital-business-cards/assets',
    'assets_url' => null,
    'lead_middleware' => ['throttle:'.RateLimits::LEADS],
    'event_middleware' => ['throttle:'.RateLimits::EVENTS],
    // Attempts per minute for the named limiters above. "per_card" bounds one
    // visitor on one card; "per_ip" bounds the same visitor across every card,
    // so spreading requests around does not lift the ceiling.
    'rate_limits' => [
        'leads' => ['per_card' => 10, 'per_ip' => 30],
        'events' => ['per_card' => 120, 'per_ip' => 600],
    ],
    'spam_protection' => [
        'enabled' => true,
        'minimum_fill_seconds' => 2,
    ],
    'lead_export' => [
        'path' => 'admin/digital-business-card-leads-export',
        'route_name' => 'admin.cards.leads.export',
        'middleware' => ['web', Authenticate::class],
        // Authorization gate for the export. The package registers a default
        // that requires an authenticated user; define this ability in the host
        // application to apply your own rules instead.
        'ability' => 'digital-business-cards.export-leads',
    ],
    'card_view' => 'digital-business-cards::cards.show',
    'storage_disk' => 'public',
    'privacy_url' => null,
    'default_theme' => [
        'background' => '#101827',
        'accent' => '#7357ff',
        'text' => '#ffffff',
    ],
    'media_directories' => [
        'avatars' => 'cards/avatars',
        'logos' => 'cards/logos',
        'covers' => 'cards/covers',
        'content' => 'cards/content',
        'galleries' => 'cards/galleries',
    ],
    // The package never sends email itself. After every contact exchange it
    // dispatches DigitalCardKit\Laravel\Events\ContactExchangeCompleted with
    // the lead identifier; the host application listens for that event and
    // implements its own delivery. These settings only apply to the optional
    // Mailable helpers (DigitalCardKit\Laravel\Mail\*) a host may send from
    // its own listener.
    'mail' => [
        'owner_subject' => 'New contact from a digital business card',
        'confirmation_subject' => 'Thank you for sharing your contact details',
        'owner_view' => 'digital-business-cards::emails.contact-exchange-received',
        'confirmation_view' => 'digital-business-cards::emails.contact-exchange-confirmation',
    ],
    'models' => [
        'card' => DigitalBusinessCard::class,
        'block' => DigitalBusinessCardBlock::class,
        'lead' => DigitalBusinessCardLead::class,
        'event' => DigitalBusinessCardEvent::class,
    ],
];
