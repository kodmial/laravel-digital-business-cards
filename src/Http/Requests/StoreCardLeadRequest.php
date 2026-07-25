<?php

namespace DigitalCardKit\Laravel\Http\Requests;

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class StoreCardLeadRequest extends FormRequest
{
    use ResolvesPublicCard;

    /** Columns the lead table stores directly; everything else is custom data. */
    private const NATIVE_KEYS = ['name', 'phone', 'email', 'company', 'comment'];

    public function card(): DigitalBusinessCard
    {
        $card = $this->resolvedCard ??= $this->resolvePublishedCard((string) $this->route('card'));

        // A card with the exchange form switched off has to look exactly like
        // a card that does not exist.
        abort_unless($card->lead_form_enabled, 404);

        return $card;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        $card = $this->card();
        $rules = [];

        foreach ($card->validatableLeadFields() as $field) {
            $rules[(string) $field['key']] = array_filter([
                ($field['required'] ?? false) ? 'required' : 'nullable',
                'string',
                'max:2000',
                ($field['type'] ?? 'text') === 'email' ? 'email:rfc' : null,
            ]);
        }

        $rules['consent'] = $card->lead_consent_required
            ? ['required', 'accepted']
            : ['sometimes', 'accepted'];

        return $rules;
    }

    /**
     * The submission mapped onto the lead columns. Keys the card does not
     * declare natively are preserved under custom_data.
     *
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
