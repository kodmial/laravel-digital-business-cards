<?php

namespace DigitalCardKit\Laravel\Support;

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

/**
 * Builds validation rules and persistence attributes for configurable leads.
 *
 * Known lead fields remain top-level columns while all configured custom
 * fields retain the package's existing custom_data representation.
 */
final class LeadFormData
{
    private const NATIVE_KEYS = ['name', 'phone', 'email', 'company', 'comment'];

    /**
     * Build rules for the card's current dynamic lead fields.
     *
     * @return array<string, array<int, string|\Closure>>
     */
    public static function rules(DigitalBusinessCard $card): array
    {
        $rules = [];
        $phoneUtil = PhoneNumberUtil::getInstance();

        foreach ($card->validatableLeadFields() as $field) {
            $fieldType = $field['type'] ?? 'text';
            $fieldRules = [
                ($field['required'] ?? false) ? 'required' : 'nullable',
                'string',
                'max:2000',
            ];

            if ($fieldType === 'email') {
                $fieldRules[] = 'email:rfc';
            }

            if ($fieldType === 'tel') {
                $fieldRules[] = static function (string $attribute, mixed $value, \Closure $fail) use ($phoneUtil): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }

                    foreach (Config::phoneCandidateRegions() as $region) {
                        try {
                            $phoneNumber = $phoneUtil->parse($value, $region);
                            if ($phoneUtil->isValidNumber($phoneNumber)) {
                                return;
                            }
                        } catch (NumberParseException) {
                            continue;
                        }
                    }

                    try {
                        $phoneNumber = $phoneUtil->parse($value, null);
                    } catch (NumberParseException) {
                        $phoneNumber = null;
                    }

                    if ($phoneNumber !== null && $phoneUtil->isValidNumber($phoneNumber)) {
                        return;
                    }

                    $fail(__('digital-business-cards::messages.lead.invalid_phone', [
                        'attribute' => $attribute,
                    ]));
                };
            }

            $rules[(string) $field['key']] = $fieldRules;
        }

        $rules['consent'] = $card->getAttribute('lead_consent_required')
            ? ['required', 'accepted']
            : ['sometimes', 'accepted'];

        return $rules;
    }

    /**
     * Convert validated form values to lead model attributes.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function attributes(array $validated, bool $consentGiven, ?string $referer): array
    {
        $validated = Arr::except($validated, ['consent']);

        return array_merge(
            array_combine(
                self::NATIVE_KEYS,
                array_map(static fn (string $key): mixed => $validated[$key] ?? null, self::NATIVE_KEYS),
            ),
            [
                'custom_data' => Arr::except($validated, self::NATIVE_KEYS),
                'consent_given' => $consentGiven,
                'source' => Str::limit((string) $referer, 255, ''),
                'submitted_at' => now(),
            ],
        );
    }
}
