<?php

namespace DigitalCardKit\Laravel\Support;

use Spatie\Color\Exceptions\InvalidColorValue;
use Spatie\Color\Hex;
use Spatie\Color\Rgb;

final class CardTheme
{
    /** @return array<string, string|bool> */
    public static function tokens(string $background, string $accent, string $text): array
    {
        $backgroundHex = self::toHex($background, '#101827');
        $accentHex = self::toHex($accent, '#7357ff');
        $textHex = self::toHex($text, '#ffffff');

        $backgroundRgb = $backgroundHex->toRgb();
        $dark = self::luminance($backgroundRgb) < .34;

        $white = new Rgb(255, 255, 255);
        $black = new Rgb(0, 0, 0);
        $surfaceBase = new Rgb(243, 244, 246);

        $accentRgb = $accentHex->toRgb();

        return [
            'background' => (string) $backgroundHex,
            'accent' => (string) $accentHex,
            'text' => (string) $textHex,
            'is_dark' => $dark,
            'surface' => (string) self::mix($backgroundRgb, $white, $dark ? .09 : .72)->toHex(),
            'surface_muted' => (string) self::mix($backgroundRgb, $dark ? $white : $black, $dark ? .15 : .035)->toHex(),
            'muted_text' => (string) self::mix($textHex->toRgb(), $backgroundRgb, .30)->toHex(),
            'border' => (string) self::mix($textHex->toRgb(), $backgroundRgb, $dark ? .18 : .14)->toHex(),
            'page_background' => (string) self::mix($backgroundRgb, $dark ? $black : $surfaceBase, $dark ? .34 : .48)->toHex(),
            'shadow' => $dark ? '0 10px 24px rgba(0, 0, 0, .28)' : '0 10px 24px rgba(15, 23, 42, .10)',
            'accent_rgb' => $accentRgb->red().', '.$accentRgb->green().', '.$accentRgb->blue(),
        ];
    }

    private static function toHex(string $value, string $fallback): Hex
    {
        try {
            return Hex::fromString(strtolower(trim($value)));
        } catch (InvalidColorValue) {
            return Hex::fromString($fallback);
        }
    }

    private static function luminance(Rgb $rgb): float
    {
        return (.2126 * $rgb->red() + .7152 * $rgb->green() + .0722 * $rgb->blue()) / 255;
    }

    private static function mix(Rgb $from, Rgb $to, float $amount): Rgb
    {
        $mix = static fn (int $start, int $end): int => (int) round($start + (($end - $start) * $amount));

        return new Rgb(
            $mix($from->red(), $to->red()),
            $mix($from->green(), $to->green()),
            $mix($from->blue(), $to->blue()),
        );
    }
}
