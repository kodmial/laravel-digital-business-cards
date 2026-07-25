<?php

namespace DigitalCardKit\Laravel\Support;

final class ContactChannelRegistry
{
    /**
     * Schemes a contact is allowed to link to from the public card. Contact
     * values arrive from a JSON column that a host application can write to
     * directly, so an unrecognised channel must never become a javascript:
     * or data: href.
     */
    private const SAFE_SCHEMES = ['http', 'https', 'tel', 'mailto'];

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
            // A value that already carries some other scheme is not a web link
            // the card can open, so it is refused rather than turned into
            // "https://javascript:...". The negative lookahead keeps
            // "example.test:8080/path" out of the scheme branch.
            return preg_match('#^[a-z][a-z0-9+.\-]*:(?!\d)#i', $value) ? '' : 'https://'.$value;
        }

        return $value;
    }

    public static function href(array $contact): string
    {
        $type = (string) ($contact['type'] ?? 'link');
        $value = trim((string) ($contact['value'] ?? ''));

        if ($value === '') {
            return '';
        }

        return self::safeUrl(match ($type) {
            'phone' => 'tel:'.preg_replace('/[^0-9+]/', '', $value),
            'email' => 'mailto:'.$value,
            'whatsapp' => preg_match('#^https?://#i', $value) ? $value : 'https://wa.me/'.preg_replace('/\D/', '', $value),
            'telegram' => self::normalize('telegram', $value),
            'max', 'website', 'link' => self::normalize($type, $value),
            'address' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($value),
            default => self::normalize('link', $value),
        });
    }

    /**
     * Group consecutive messengers so the card renders them as one row. This
     * lives next to the channel definitions, rather than in the template, so
     * the layout rule can be asserted directly.
     *
     * @param  array<int, array<string, mixed>>  $contacts
     * @return array<int, array{type: string, items: array<int, array<string, mixed>>}>
     */
    public static function group(array $contacts): array
    {
        $groups = [];

        foreach ($contacts as $contact) {
            $isMessenger = self::isMessenger($contact);
            $last = count($groups) - 1;

            if ($isMessenger && $last >= 0 && $groups[$last]['type'] === 'social') {
                $groups[$last]['items'][] = $contact;

                continue;
            }

            $groups[] = ['type' => $isMessenger ? 'social' : 'contact', 'items' => [$contact]];
        }

        return $groups;
    }

    private static function safeUrl(string $url): string
    {
        // Matched on the raw string rather than with parse_url(), which reads
        // "tel:112" as host:port and reports no scheme at all — that would
        // discard short numbers such as emergency lines and extensions.
        if (! preg_match('#^([a-z][a-z0-9+.\-]*):#i', $url, $matches)) {
            return '';
        }

        return in_array(strtolower($matches[1]), self::SAFE_SCHEMES, true) ? $url : '';
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
