<?php

namespace DigitalCardKit\Laravel\Http\Requests;

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Support\LeadFormData;
use Illuminate\Foundation\Http\FormRequest;

class StoreCardLeadRequest extends FormRequest
{
    use ResolvesPublicCard;

    public function card(): DigitalBusinessCard
    {
        $card = $this->resolvedCard ??= $this->resolvePublishedCard((string) $this->route('card'));

        abort_unless($card->lead_form_enabled, 404);

        return $card;
    }

    /** @return array<string, array<int, string|\Closure>> */
    public function rules(): array
    {
        return LeadFormData::rules($this->card());
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return LeadFormData::validationAttributes($this->card());
    }

    /** @return array<string, mixed> */
    public function leadAttributes(): array
    {
        return LeadFormData::attributes(
            $this->validated(),
            $this->boolean('consent'),
            $this->header('Referer'),
        );
    }
}
