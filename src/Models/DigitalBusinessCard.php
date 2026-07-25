<?php

namespace DigitalCardKit\Laravel\Models;

use DigitalCardKit\Laravel\Support\CardTheme;
use DigitalCardKit\Laravel\Support\ContactChannelRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DigitalBusinessCard extends Model
{
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
            'contact_methods' => 'array',
            'lead_form_fields' => 'array',
            'lead_notification_emails' => 'array',
            'lead_form_enabled' => 'boolean',
            'lead_send_confirmation' => 'boolean',
            'lead_consent_required' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (self $card): void {
            foreach (['avatar', 'logo', 'cover_image'] as $attribute) {
                if (! $card->wasChanged($attribute)) {
                    continue;
                }

                $previousPath = $card->getOriginal($attribute);
                if ($previousPath && $previousPath !== $card->{$attribute}) {
                    Storage::disk(config('digital-business-cards.storage_disk', 'public'))->delete($previousPath);
                }
            }
        });

        static::deleting(function (self $card): void {
            foreach ([$card->avatar, $card->logo, $card->cover_image] as $path) {
                if ($path) {
                    Storage::disk(config('digital-business-cards.storage_disk', 'public'))->delete($path);
                }
            }

            $card->blocks()->get()->each->deleteMedia();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(config('digital-business-cards.models.block'), 'digital_business_card_id')->orderBy('sort_order');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(config('digital-business-cards.models.lead'), 'digital_business_card_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(config('digital-business-cards.models.event'), 'digital_business_card_id');
    }

    public function getFullNameAttribute(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name])->filter()->implode(' ');
    }

    /** @return array<int, array<string, mixed>> */
    public function publicContactMethods(): array
    {
        return array_values($this->contact_methods ?: []);
    }

    public function storageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $diskName = config('digital-business-cards.storage_disk', 'public');
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
        $customTheme = $this->theme_mode === 'custom';

        return CardTheme::tokens(
            $customTheme
                ? ($this->background_color ?: '#101827')
                : config('digital-business-cards.default_theme.background', '#101827'),
            $customTheme
                ? ($this->accent_color ?: '#7357ff')
                : config('digital-business-cards.default_theme.accent', '#7357ff'),
            $customTheme
                ? ($this->text_color ?: '#ffffff')
                : config('digital-business-cards.default_theme.text', '#ffffff'),
        );
    }

    public function setContactMethodsAttribute($value): void
    {
        $methods = is_array($value) ? $value : (json_decode((string) $value, true) ?: []);
        $this->attributes['contact_methods'] = json_encode(array_map(static function (array $method): array {
            $type = (string) ($method['type'] ?? 'link');
            if (isset($method['value'])) {
                $method['value'] = ContactChannelRegistry::normalize($type, (string) $method['value']);
            }

            return $method;
        }, $methods), JSON_UNESCAPED_UNICODE);
    }

    public function publicUrl(): string
    {
        return route(config('digital-business-cards.route_name_prefix', 'cards.').'show', $this);
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
}
