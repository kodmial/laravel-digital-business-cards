<?php

namespace DigitalCardKit\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DigitalBusinessCardBlock extends Model
{
    protected $table = 'digital_business_card_blocks';

    protected $fillable = ['type', 'title', 'content', 'url', 'button_label', 'data', 'sort_order', 'is_enabled'];

    protected function casts(): array
    {
        return ['data' => 'array', 'is_enabled' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::updated(function (self $block): void {
            if (! $block->wasChanged('data')) {
                return;
            }

            $previous = self::mediaPaths($block->getOriginal('data'));
            $current = self::mediaPaths($block->data);
            self::deletePaths(array_diff($previous, $current));
        });

        static::deleting(fn (self $block) => $block->deleteMedia());
    }

    public function deleteMedia(): void
    {
        self::deletePaths(self::mediaPaths($this->data));
    }

    /** @return array<int, string> */
    private static function mediaPaths(mixed $data): array
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (! is_array($data)) {
            return [];
        }

        return array_values(array_filter(array_merge(
            [(string) ($data['media'] ?? '')],
            array_map('strval', is_array($data['gallery'] ?? null) ? $data['gallery'] : []),
        )));
    }

    /** @param array<int, string> $paths */
    private static function deletePaths(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk(config('digital-business-cards.storage_disk', 'public'))->delete($paths);
        }
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(config('digital-business-cards.models.card'), 'digital_business_card_id');
    }
}
