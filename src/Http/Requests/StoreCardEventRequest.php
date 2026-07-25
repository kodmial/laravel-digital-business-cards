<?php

namespace DigitalCardKit\Laravel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCardEventRequest extends FormRequest
{
    use ResolvesPublicCard;

    /** Interactions the public card is allowed to report. */
    public const TYPES = ['share', 'vcard', 'cta', 'contact', 'gallery', 'file', 'video'];

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $card = $this->card();
        $blocks = $card->blocks()->getRelated();

        return [
            'type' => ['required', Rule::in(self::TYPES)],
            // A block reference is only meaningful when it belongs to this
            // card and is currently visible, so that is expressed as an
            // existence rule rather than a separate lookup in the controller.
            'block_id' => [
                'nullable',
                'integer',
                Rule::exists($blocks->getTable(), $blocks->getKeyName())
                    ->where('digital_business_card_id', $card->getKey())
                    ->where('is_enabled', true),
            ],
        ];
    }

    public function blockId(): ?int
    {
        $blockId = $this->validated()['block_id'] ?? null;

        return $blockId === null ? null : (int) $blockId;
    }
}
