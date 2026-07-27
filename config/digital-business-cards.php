<?php

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardBlock;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardEvent;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardLead;
use DigitalCardKit\Laravel\Notifications\LaravelMailNotificationSender;
use DigitalCardKit\Laravel\Support\RateLimits;

return [
    'route_prefix' => 'card',
    'route_name_prefix' => 'cards.',
    'route_middleware' => ['web'],
    'asset_route_prefix' => 'digital-business-cards/assets',
    'assets_url' => null,
    'use_livewire' => false,
    'lead_middleware' => ['throttle:'.RateLimits::LEADS],
    'event_middleware' => ['throttle:'.RateLimits::EVENTS],
    // Attempts per minute for the named limiters above. "per_card" bounds one
    // visitor on one card; "per_ip" bounds the same visitor across every card,
    // so spreading requests around does not lift the ceiling.
    'rate_limits' => [
        'per_card' => 10,
        'per_ip' => 50,
    ],
    'models' => [
        'card' => DigitalBusinessCard::class,
        'block' => DigitalBusinessCardBlock::class,
        'lead' => DigitalBusinessCardLead::class,
        'event' => DigitalBusinessCardEvent::class,
    ],
    'media_directories' => [
        'disk' => 'public',
        'card_avatar' => 'digital-business-cards/avatars',
        'card_cover' => 'digital-business-cards/covers',
        'card_logo' => 'digital-business-cards/logos',
        'block_media' => 'digital-business-cards/blocks',
    ],
    'default_theme' => [
        'mode' => 'custom',
        'background' => '#101827',
        'accent' => '#7357ff',
        'text' => '#ffffff',
    ],
    'notification_sender' => LaravelMailNotificationSender::class,
    'notifications' => [
        'queued' => false,
        'register_default_listener' => true,
        'send_confirmation' => false,
        'from_address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
        'from_name' => env('MAIL_FROM_NAME', 'Digital Business Cards'),
    ],
    'privacy_url' => '',
    'font_default' => 'system',
    'fonts' => [
        'system' => ['family' => 'system-ui, -apple-system, sans-serif'],
        'serif' => ['family' => 'Georgia, serif'],
        'mono' => ['family' => 'ui-monospace, monospace'],
    ],
    'contact_methods' => [
        'phone' => ['label' => 'Phone', 'group' => 'phone'],
        'email' => ['label' => 'Email', 'group' => 'email'],
        'website' => ['label' => 'Website', 'group' => 'website'],
        'telegram' => ['label' => 'Telegram', 'group' => 'messenger'],
        'whatsapp' => ['label' => 'WhatsApp', 'group' => 'messenger'],
        'linkedin' => ['label' => 'LinkedIn', 'group' => 'social'],
        'facebook' => ['label' => 'Facebook', 'group' => 'social'],
        'instagram' => ['label' => 'Instagram', 'group' => 'social'],
        'twitter' => ['label' => 'Twitter', 'group' => 'social'],
        'youtube' => ['label' => 'YouTube', 'group' => 'social'],
        'tiktok' => ['label' => 'TikTok', 'group' => 'social'],
        'github' => ['label' => 'GitHub', 'group' => 'social'],
        'viber' => ['label' => 'Viber', 'group' => 'messenger'],
        'max' => ['label' => 'MAX', 'group' => 'messenger'],
        'skype' => ['label' => 'Skype', 'group' => 'messenger'],
        'zoom' => ['label' => 'Zoom', 'group' => 'video'],
        'teams' => ['label' => 'Microsoft Teams', 'group' => 'video'],
        'calendar' => ['label' => 'Calendar', 'group' => 'scheduling'],
        'x' => ['label' => 'X', 'group' => 'social'],
    ],
];
