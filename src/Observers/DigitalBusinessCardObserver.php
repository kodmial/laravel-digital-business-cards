<?php

namespace DigitalCardKit\Laravel\Observers;

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Support\Config;
use Illuminate\Support\Facades\Storage;

/**
 * Keeps the configured disk in step with a card's media attributes, so a
 * replaced or deleted image does not linger as an orphaned file.
 */
class DigitalBusinessCardObserver
{
    public function updated(DigitalBusinessCard $card): void
    {
        $replaced = [];

        foreach (DigitalBusinessCard::MEDIA_ATTRIBUTES as $attribute) {
            $previous = $card->wasChanged($attribute) ? (string) $card->getOriginal($attribute) : '';

            if ($previous !== '' && $previous !== (string) $card->{$attribute}) {
                $replaced[] = $previous;
            }
        }

        $this->delete($replaced);
    }

    public function deleting(DigitalBusinessCard $card): void
    {
        $this->delete($card->mediaPaths());

        $card->blocks()->get()->each->deleteMedia();
    }

    /** @param  array<int, string>  $paths */
    private function delete(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk(Config::disk())->delete($paths);
        }
    }
}
