<?php

namespace DigitalCardKit\Laravel\Casts;

use DigitalCardKit\Laravel\Support\ContactChannelRegistry;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores contact methods as JSON, normalising each value on the way in.
 *
 * Normalisation belongs to the cast rather than to a mutator so that reading
 * and writing the attribute are described in one place; a plain 'array' cast
 * paired with a separate mutator left the two halves able to drift apart.
 *
 * @implements CastsAttributes<array<int, array<string, mixed>>, iterable<array-key, array<string, mixed>>>
 */
class ContactMethods implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, array<string, mixed>>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        $decoded = is_array($value) ? $value : json_decode((string) $value, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    /** @param  array<string, mixed>  $attributes */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $methods = is_array($value) ? $value : (json_decode((string) $value, true) ?: []);

        return (string) json_encode(
            array_values(array_map(self::normalizeMethod(...), array_filter($methods, 'is_array'))),
            JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * @param  array<string, mixed>  $method
     * @return array<string, mixed>
     */
    private static function normalizeMethod(array $method): array
    {
        if (isset($method['value'])) {
            $method['value'] = ContactChannelRegistry::normalize(
                (string) ($method['type'] ?? 'link'),
                (string) $method['value'],
            );
        }

        return $method;
    }
}
