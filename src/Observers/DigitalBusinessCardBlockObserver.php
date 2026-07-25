<?php

namespace DigitalCardKit\Laravel\Observers;

use DigitalCardKit\Laravel\Models\DigitalBusinessCardBlock;

/**
 * Block media lives inside the block's data payload, so replacements are
 * detected by diffing that payload rather than by watching named columns.
 */
class DigitalBusinessCardBlockObserver
{
    public function updated(DigitalBusinessCardBlock $block): void
    {
        if ($block->wasChanged('data')) {
            $block->deleteReplacedMedia();
        }
    }

    public function deleting(DigitalBusinessCardBlock $block): void
    {
        $block->deleteMedia();
    }
}
