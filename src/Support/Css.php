<?php

namespace DigitalCardKit\Laravel\Support;

final class Css
{
    /**
     * Build a CSS url() token that is safe inside an HTML style attribute.
     *
     * Blade escaping alone is not enough here. The browser decodes &#039;
     * back into an apostrophe before the CSS parser runs, so a media path
     * containing a quote could otherwise close the url() and append CSS
     * declarations of its own. Escaping the quote for the CSS parser first
     * survives that decoding step.
     */
    public static function url(string $url): string
    {
        $url = preg_replace('/[\p{Cc}\p{Zl}\p{Zp}]/u', '', $url) ?? '';

        return "url('".str_replace(['\\', "'"], ['\\\\', "\\'"], $url)."')";
    }
}
