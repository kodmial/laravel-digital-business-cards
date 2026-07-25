<?php

namespace DigitalCardKit\Laravel\Support;

final class ContactChannelRegistry
{
    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            'phone' => __('digital-business-cards::messages.channels.phone'),
            'email' => __('digital-business-cards::messages.channels.email'),
            'telegram' => 'Telegram',
            'max' => 'MAX',
            'website' => __('digital-business-cards::messages.channels.website'),
            'whatsapp' => 'WhatsApp',
            'address' => __('digital-business-cards::messages.channels.address'),
            'link' => __('digital-business-cards::messages.channels.link'),
        ];
    }

    public static function normalize(string $type, string $value): string
    {
        $value = trim($value);

        if ($type === 'telegram') {
            $value = preg_replace('#^(?:https?://)?(?:www\.)?t\.me/#i', '', $value) ?? $value;
            $value = preg_replace('#^tg://resolve\?domain=#i', '', $value) ?? $value;
            $handle = ltrim(trim($value), '@');

            return $handle === '' ? '' : 'https://t.me/'.$handle;
        }

        if (in_array($type, ['max', 'website', 'link'], true) && $value !== '' && ! preg_match('#^https?://#i', $value)) {
            return 'https://'.$value;
        }

        return $value;
    }

    public static function href(array $contact): string
    {
        $type = (string) ($contact['type'] ?? 'link');
        $value = trim((string) ($contact['value'] ?? ''));

        return match ($type) {
            'phone' => 'tel:'.preg_replace('/[^0-9+]/', '', $value),
            'email' => 'mailto:'.$value,
            'whatsapp' => str_starts_with($value, 'http') ? $value : 'https://wa.me/'.preg_replace('/\D/', '', $value),
            'telegram' => self::normalize('telegram', $value),
            'max', 'website', 'link' => self::normalize($type, $value),
            'address' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($value),
            default => $value,
        };
    }

    public static function label(array $contact): string
    {
        return trim((string) ($contact['label'] ?? '')) ?: match ($contact['type'] ?? 'link') {
            'phone' => __('digital-business-cards::messages.actions.phone'),
            'email' => __('digital-business-cards::messages.actions.email'),
            'telegram' => 'Telegram',
            'max' => 'MAX',
            'website' => __('digital-business-cards::messages.channels.website'),
            'whatsapp' => 'WhatsApp',
            'address' => __('digital-business-cards::messages.channels.address'),
            default => __('digital-business-cards::messages.actions.open_link'),
        };
    }

    public static function isMessenger(array $contact): bool
    {
        return in_array($contact['type'] ?? '', ['telegram', 'max', 'whatsapp'], true);
    }

    public static function displayValue(array $contact): string
    {
        $type = (string) ($contact['type'] ?? 'link');
        $value = trim((string) ($contact['value'] ?? ''));

        if ($type === 'website') {
            return self::websiteDomain($value);
        }

        if ($type !== 'phone') {
            return $value;
        }

        $digits = preg_replace('/\D/', '', $value);
        if (! preg_match('/^[78](\d{10})$/', (string) $digits, $matches)) {
            return $value;
        }

        $prefix = str_starts_with($value, '+') ? '+7' : $digits[0];

        return sprintf('%s (%s) %s-%s-%s', $prefix, substr($matches[1], 0, 3), substr($matches[1], 3, 3), substr($matches[1], 6, 2), substr($matches[1], 8, 2));
    }

    private static function websiteDomain(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $normalized = self::normalize('website', $value);
        $host = parse_url($normalized, PHP_URL_HOST);

        if (is_string($host) && $host !== '' && ! preg_match('/\s/u', $host)) {
            $host = preg_replace('/^www\./i', '', rtrim($host, '.')) ?? $host;

            if (function_exists('idn_to_utf8') && preg_match('/(^|\.)xn--/i', $host)) {
                $unicodeHost = idn_to_utf8($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
                if (is_string($unicodeHost) && $unicodeHost !== '') {
                    $host = $unicodeHost;
                }
            }

            return $host;
        }

        return preg_replace('#^(?:https?://)?(?:www\.)?#i', '', $value) ?? $value;
    }
}
