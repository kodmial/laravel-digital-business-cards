<?php

namespace DigitalCardKit\Laravel\Http\Requests;

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Support\Config;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

class StoreCardLeadRequest extends FormRequest
{
    use ResolvesPublicCard;

    private const NATIVE_KEYS = ['name', 'phone', 'email', 'company', 'comment'];

    public function card(): DigitalBusinessCard
    {
        $card = $this->resolvedCard ??= $this->resolvePublishedCard((string) $this->route('card'));

        abort_unless($card->lead_form_enabled, 404);

        return $card;
    }

    /** @return array<string, array<int, string|\Closure>> */
    public function rules(): array
    {
        $card = $this->card();
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

                    $candidates = Config::phoneCandidateRegions();
                    foreach ($candidates as $region) {
                        try {
                            $proto = $phoneUtil->parse($value, $region);
                            if ($phoneUtil->isValidNumber($proto)) {
                                return;
                            }
                        } catch (NumberParseException) {
                            continue;
                        }
                    }

                    $proto = null;
                    try {
                        $proto = $phoneUtil->parse($value, null);
                    } catch (NumberParseException) {
                        $proto = null;
                    }

                    if ($proto !== null && $phoneUtil->isValidNumber($proto)) {
                        return;
                    }

                    $fail(__('validation.phone', ['attribute' => $attribute]));
                };
            }

            $rules[(string) $field['key']] = $fieldRules;
        }

        $rules['consent'] = $card->lead_consent_required
            ? ['required', 'accepted']
            : ['sometimes', 'accepted'];

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function leadAttributes(): array
    {
        $validated = Arr::except($this->validated(), ['consent']);

        return array_merge(
            array_combine(
                self::NATIVE_KEYS,
                array_map(static fn (string $key): mixed => $validated[$key] ?? null, self::NATIVE_KEYS),
            ),
            [
                'custom_data' => Arr::except($validated, self::NATIVE_KEYS),
                'consent_given' => $this->boolean('consent'),
                'source' => Str::limit((string) $this->header('Referer'), 255, ''),
                'submitted_at' => now(),
            ],
        );
    }
}
