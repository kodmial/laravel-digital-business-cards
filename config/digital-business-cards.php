<?php

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardBlock;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardEvent;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardLead;
use DigitalCardKit\Laravel\Notifications\LaravelMailNotificationSender;
use Filament\Http\Middleware\Authenticate;

return [
    'route_prefix' => 'card',
    'route_name_prefix' => 'cards.',
    'route_middleware' => ['web'],
    'asset_route_prefix' => 'digital-business-cards/assets',
    'assets_url' => null,
    'lead_middleware' => ['throttle:10,1'],
    'event_middleware' => ['throttle:120,1'],
    'lead_export' => [
        'path' => 'admin/digital-business-card-leads-export',
        'route_name' => 'admin.cards.leads.export',
        'middleware' => ['web', Authenticate::class],
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
    'notifications' => [
        'register_default_listener' => true,
        'queued' => false,
        'queue_connection' => null,
        'queue_name' => null,
    ],
    'notification_sender' => LaravelMailNotificationSender::class,
    'mail' => [
        'mailer' => null,
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
