<?php

namespace DigitalCardKit\Laravel\Http\Requests;

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Support\ResolvesModels;

trait ResolvesPublicCard
{
    use ResolvesModels;

    private ?DigitalBusinessCard $resolvedCard = null;

    /**
     * The card this request targets. Resolved once, because the rule set and
     * the controller both need it within a single request.
     */
    public function card(): DigitalBusinessCard
    {
        return $this->resolvedCard ??= $this->resolvePublishedCard((string) $this->route('card'));
    }
}
