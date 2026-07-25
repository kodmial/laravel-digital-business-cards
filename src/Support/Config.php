<?php

namespace DigitalCardKit\Laravel\Support;

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardBlock;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardEvent;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardLead;
use Illuminate\Database\Eloquent\Model;

/**
 * Single point of access to the package configuration.
 *
 * Each setting has exactly one fallback here instead of a literal repeated at
 * every call site, so a published configuration that omits a key cannot behave
 * differently depending on which class happened to read it.
 */
final class Config
{
    private const MODELS = [
        'card' => DigitalBusinessCard::class,
        'block' => DigitalBusinessCardBlock::class,
        'lead' => DigitalBusinessCardLead::class,
        'event' => DigitalBusinessCardEvent::class,
    ];

    private const THEME = [
        'background' => '#101827',
        'accent' => '#7357ff',
        'text' => '#ffffff',
    ];

    private const MEDIA_DIRECTORIES = [
        'avatars' => 'cards/avatars',
        'logos' => 'cards/logos',
        'covers' => 'cards/covers',
        'content' => 'cards/content',
        'galleries' => 'cards/galleries',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        return config('digital-business-cards.'.$key, $default);
    }

    /** @return class-string<Model> */
    public static function model(string $key): string
    {
        return self::get('models.'.$key, self::MODELS[$key]);
    }

    /**
     * The card model, typed narrowly. A host may substitute its own class but
     * it has to extend the packaged model, so callers can rely on that shape.
     *
     * @return class-string<DigitalBusinessCard>
     */
    public static function cardModel(): string
    {
        return self::get('models.card', DigitalBusinessCard::class);
    }

    public static function disk(): string
    {
        return (string) self::get('storage_disk', 'public');
    }

    public static function routePrefix(): string
    {
        return trim((string) self::get('route_prefix', 'card'), '/');
    }

    public static function assetRoutePrefix(): string
    {
        return trim((string) self::get('asset_route_prefix', 'digital-business-cards/assets'), '/');
    }

    public static function routeName(string $suffix = ''): string
    {
        return (string) self::get('route_name_prefix', 'cards.').$suffix;
    }

    public static function cardView(): string
    {
        return (string) self::get('card_view', 'digital-business-cards::cards.show');
    }

    public static function mediaDirectory(string $key): string
    {
        return (string) self::get('media_directories.'.$key, self::MEDIA_DIRECTORIES[$key]);
    }

    public static function defaultThemeColor(string $key): string
    {
        return (string) self::get('default_theme.'.$key, self::THEME[$key]);
    }

    public static function leadExportAbility(): string
    {
        return (string) self::get('lead_export.ability', 'digital-business-cards.export-leads');
    }

    public static function privacyUrl(): string
    {
        return trim((string) self::get('privacy_url', ''));
    }

    public static function leadExportRouteName(): string
    {
        return (string) self::get('lead_export.route_name', 'admin.cards.leads.export');
    }

    public static function mailSubject(string $key): string
    {
        $configured = (string) self::get('mail.'.$key.'_subject', '');

        if ($configured !== '') {
            return $configured;
        }

        return __('digital-business-cards::messages.mail.'.$key.'_subject');
    }

    public static function mailView(string $key): string
    {
        return (string) self::get(
            'mail.'.$key.'_view',
            'digital-business-cards::emails.contact-exchange-'.($key === 'owner' ? 'received' : 'confirmation'),
        );
    }

    /** @return array<int, mixed> */
    public static function middleware(string $key, array $default = ['web']): array
    {
        return (array) self::get($key, $default);
    }
}
