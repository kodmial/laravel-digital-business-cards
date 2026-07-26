<?php

namespace DigitalCardKit\Laravel\Support;

use League\Uri\Uri;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

final class ContactChannelRegistry
{
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
        try {
            $uri = Uri::new($url);
            $scheme = $uri->getScheme();
        } catch (\Throwable) {
            return '';
        }

        if ($scheme === null) {
            return '';
        }

        return in_array(strtolower($scheme), self::SAFE_SCHEMES, true) ? (string) $uri : '';
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

        return self::formatPhoneInternational($value);
    }

    private static function formatPhoneInternational(string $value): string
    {
        static $phoneUtil = null;
        $phoneUtil ??= PhoneNumberUtil::getInstance();

        try {
            $regionCodes = Config::phoneRegionCodes();
            foreach ($regionCodes as $region => $expectedCountryCodes) {
                try {
                    $proto = $phoneUtil->parse($value, $region);
                    if (! $phoneUtil->isValidNumber($proto)) {
                        continue;
                    }
                    if ($expectedCountryCodes !== null && ! in_array($proto->getCountryCode(), $expectedCountryCodes, true)) {
                        continue;
                    }

                    return match ($region) {
                        'RU', 'KZ' => self::formatRuKzMask($phoneUtil->format($proto, PhoneNumberFormat::E164)),
                        'BY' => self::formatByMask($phoneUtil->format($proto, PhoneNumberFormat::E164)),
                        default => $phoneUtil->format($proto, PhoneNumberFormat::INTERNATIONAL),
                    };
                } catch (NumberParseException) {
                    continue;
                }
            }

            try {
                $proto = $phoneUtil->parse($value, null);

                return $phoneUtil->format($proto, PhoneNumberFormat::INTERNATIONAL);
            } catch (NumberParseException) {
                return $value;
            }
        } catch (\Throwable) {
            return $value;
        }
    }

    private static function formatRuKzMask(string $e164): string
    {
        if (! preg_match('/^\+7(\d{3})(\d{3})(\d{2})(\d{2})$/', $e164, $m)) {
            return $e164;
        }

        return sprintf('+7 (%s) %s-%s-%s', $m[1], $m[2], $m[3], $m[4]);
    }

    private static function formatByMask(string $e164): string
    {
        if (! preg_match('/^\+375(\d{2})(\d{3})(\d{2})(\d{2})$/', $e164, $m)) {
            return $e164;
        }

        return sprintf('+375 (%s) %s-%s-%s', $m[1], $m[2], $m[3], $m[4]);
    }

    private static function websiteDomain(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $normalized = self::normalize('website', $value);

        try {
            $uri = Uri::new($normalized);
            $host = $uri->getHost();
        } catch (\Throwable) {
            $host = null;
        }

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
