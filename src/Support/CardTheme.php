<?php

namespace DigitalCardKit\Laravel\Support;

final class CardTheme
{
    /** @return array<string, string|bool> */
    public static function tokens(string $background, string $accent, string $text): array
    {
        $background = self::hex($background, '#101827');
        $accent = self::hex($accent, '#7357ff');
        $text = self::hex($text, '#ffffff');
        $dark = self::luminance($background) < .34;

        return [
            'background' => $background,
            'accent' => $accent,
            'text' => $text,
            'is_dark' => $dark,
            'surface' => self::mix($background, '#ffffff', $dark ? .09 : .72),
            'surface_muted' => self::mix($background, $dark ? '#ffffff' : '#000000', $dark ? .15 : .035),
            'muted_text' => self::mix($text, $background, .30),
            'border' => self::mix($text, $background, $dark ? .18 : .14),
            'page_background' => $dark ? self::mix($background, '#000000', .34) : self::mix($background, '#f3f4f6', .48),
            'shadow' => $dark ? '0 10px 24px rgba(0, 0, 0, .28)' : '0 10px 24px rgba(15, 23, 42, .10)',
            'accent_rgb' => implode(', ', self::rgb($accent)),
        ];
    }

    private static function hex(string $value, string $fallback): string
    {
        $value = trim($value);

        return preg_match('/^#[0-9a-f]{6}$/i', $value) ? strtolower($value) : $fallback;
    }

    /** @return array{int, int, int} */
    private static function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    private static function luminance(string $hex): float
    {
        [$red, $green, $blue] = self::rgb($hex);

        return (.2126 * $red + .7152 * $green + .0722 * $blue) / 255;
    }

    private static function mix(string $from, string $to, float $amount): string
    {
        [$fromRed, $fromGreen, $fromBlue] = self::rgb($from);
        [$toRed, $toGreen, $toBlue] = self::rgb($to);
        $mix = static fn (int $start, int $end): int => (int) round($start + (($end - $start) * $amount));

        return sprintf('#%02x%02x%02x', $mix($fromRed, $toRed), $mix($fromGreen, $toGreen), $mix($fromBlue, $toBlue));
    }
}
