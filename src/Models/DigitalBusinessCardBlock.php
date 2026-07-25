<?php

namespace DigitalCardKit\Laravel\Models;

use DigitalCardKit\Laravel\Database\Factories\DigitalBusinessCardBlockFactory;
use DigitalCardKit\Laravel\Observers\DigitalBusinessCardBlockObserver;
use DigitalCardKit\Laravel\Support\Config;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[ObservedBy([DigitalBusinessCardBlockObserver::class])]
class DigitalBusinessCardBlock extends Model
{
    use HasFactory;

    protected $table = 'digital_business_card_blocks';

    protected $fillable = ['type', 'title', 'content', 'url', 'button_label', 'data', 'sort_order', 'is_enabled'];

    protected function casts(): array
    {
        return ['data' => 'array', 'is_enabled' => 'boolean'];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Config::model('card'), 'digital_business_card_id');
    }

    /** Remove every file this block currently references. */
    public function deleteMedia(): void
    {
        self::deletePaths(self::mediaPaths($this->data));
    }

    /**
     * Remove files that the last save dropped from the block. Called after an
     * update, when getOriginal('data') still holds the previous payload.
     */
    public function deleteReplacedMedia(): void
    {
        self::deletePaths(array_diff(
            self::mediaPaths($this->getOriginal('data')),
            self::mediaPaths($this->data),
        ));
    }

    /** @return array<int, string> */
    public static function mediaPaths(mixed $data): array
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

    /** @param  array<int, string>  $paths */
    private static function deletePaths(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk(Config::disk())->delete(array_values($paths));
        }
    }

    protected static function newFactory(): DigitalBusinessCardBlockFactory
    {
        return DigitalBusinessCardBlockFactory::new();
    }
}
