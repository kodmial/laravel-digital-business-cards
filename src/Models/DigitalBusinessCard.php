<?php

namespace DigitalCardKit\Laravel\Models;

use DigitalCardKit\Laravel\Casts\ContactMethods;
use DigitalCardKit\Laravel\Database\Factories\DigitalBusinessCardFactory;
use DigitalCardKit\Laravel\Observers\DigitalBusinessCardObserver;
use DigitalCardKit\Laravel\Support\CardTheme;
use DigitalCardKit\Laravel\Support\Config;
use DigitalCardKit\Laravel\Support\ContactChannelRegistry;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[ObservedBy([DigitalBusinessCardObserver::class])]
class DigitalBusinessCard extends Model
{
    use HasFactory;

    /**
     * Lead field keys become request input names and array keys in
     * custom_data, so they are restricted to a shape that is safe in both.
     */
    public const LEAD_FIELD_KEY_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/';

    /** Attributes holding a single managed file on the configured disk. */
    public const MEDIA_ATTRIBUTES = ['avatar', 'logo', 'cover_image'];

    protected $table = 'digital_business_cards';

    protected $attributes = ['is_published' => false];

    protected $fillable = [
        'slug', 'is_published', 'first_name', 'last_name', 'middle_name', 'job_title',
        'company_name', 'avatar', 'logo', 'cover_image', 'headline', 'about', 'contact_methods',
        'background_color', 'accent_color', 'text_color', 'theme_mode', 'button_style', 'font_family',
        'lead_form_enabled', 'lead_form_title', 'lead_form_description', 'lead_form_fields',
        'lead_notification_emails', 'lead_send_confirmation', 'lead_confirmation_subject',
        'privacy_url', 'lead_consent_required', 'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'contact_methods' => ContactMethods::class,
            'lead_form_fields' => 'array',
            'lead_notification_emails' => 'array',
            'lead_form_enabled' => 'boolean',
            'lead_send_confirmation' => 'boolean',
            'lead_consent_required' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @param  Builder<static>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Config::model('block'), 'digital_business_card_id')->orderBy('sort_order');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Config::model('lead'), 'digital_business_card_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Config::model('event'), 'digital_business_card_id');
    }

    /**
     * Paths on the configured disk whose lifetime this card owns.
     *
     * @return array<int, string>
     */
    public function mediaPaths(): array
    {
        return array_values(array_filter(array_map(
            fn (string $attribute): string => (string) $this->{$attribute},
            self::MEDIA_ATTRIBUTES,
        )));
    }

    public function getFullNameAttribute(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name])->filter()->implode(' ');
    }

    /**
     * Contact methods that can actually be opened. A stored value with an
     * unsupported scheme yields no href, and rendering it would leave a dead
     * link on the card, so it is dropped here rather than in the template.
     *
     * @return array<int, array<string, mixed>>
     */
    public function publicContactMethods(): array
    {
        return array_values(array_filter(
            $this->contact_methods ?: [],
            static fn (array $contact): bool => ContactChannelRegistry::href($contact) !== '',
        ));
    }

    public function storageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $diskName = Config::disk();
        $url = Storage::disk($diskName)->url($path);
        $disk = config("filesystems.disks.{$diskName}", []);

        if (($disk['driver'] ?? null) !== 'local') {
            return $url;
        }

        $urlHost = parse_url($url, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $usesApplicationOrigin = $urlHost === null
            || ($appHost !== null && strcasecmp((string) $urlHost, (string) $appHost) === 0);

        if (! $usesApplicationOrigin) {
            return $url;
        }

        $relative = parse_url($url, PHP_URL_PATH) ?: '/'.ltrim($path, '/');
        $query = parse_url($url, PHP_URL_QUERY);
        $fragment = parse_url($url, PHP_URL_FRAGMENT);

        return $relative
            .($query !== null ? '?'.$query : '')
            .($fragment !== null ? '#'.$fragment : '');
    }

    /** @return array<string, string|bool> */
    public function themeTokens(): array
    {
        $custom = $this->theme_mode === 'custom';
        $color = fn (string $key, ?string $own): string => $custom
            ? ($own ?: Config::defaultThemeColor($key))
            : Config::defaultThemeColor($key);

        return CardTheme::tokens(
            $color('background', $this->background_color),
            $color('accent', $this->accent_color),
            $color('text', $this->text_color),
        );
    }

    public function publicUrl(): string
    {
        return route(Config::routeName('show'), $this);
    }

    public function vcardFilename(): string
    {
        return Str::slug($this->full_name) ?: 'contact';
    }

    /** @return array<int, array<string, mixed>> */
    public function leadFields(): array
    {
        return $this->lead_form_fields ?: [
            ['key' => 'name', 'label' => __('digital-business-cards::messages.fields.name'), 'type' => 'text', 'required' => true],
            ['key' => 'phone', 'label' => __('digital-business-cards::messages.fields.phone'), 'type' => 'tel', 'required' => true],
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => false],
            ['key' => 'company', 'label' => __('digital-business-cards::messages.fields.company'), 'type' => 'text', 'required' => false],
            ['key' => 'comment', 'label' => __('digital-business-cards::messages.fields.comment'), 'type' => 'textarea', 'required' => false],
        ];
    }

    /**
     * Lead fields whose key is usable as request input. A card configured
     * with a malformed key keeps working; that one field is simply not
     * collected.
     *
     * @return array<int, array<string, mixed>>
     */
    public function validatableLeadFields(): array
    {
        return array_values(array_filter(
            $this->leadFields(),
            static fn (array $field): bool => (bool) preg_match(
                self::LEAD_FIELD_KEY_PATTERN,
                (string) ($field['key'] ?? ''),
            ),
        ));
    }

    protected static function newFactory(): DigitalBusinessCardFactory
    {
        return DigitalBusinessCardFactory::new();
    }
}
