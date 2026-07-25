<?php

namespace DigitalCardKit\Laravel\Models;

use DigitalCardKit\Laravel\Database\Factories\DigitalBusinessCardEventFactory;
use DigitalCardKit\Laravel\Support\Config;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalBusinessCardEvent extends Model
{
    use HasFactory;

    protected $table = 'digital_business_card_events';

    public $timestamps = false;

    protected $fillable = ['type', 'digital_business_card_block_id', 'visitor_hash', 'metadata', 'occurred_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'datetime'];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Config::model('card'), 'digital_business_card_id');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Config::model('block'), 'digital_business_card_block_id');
    }

    protected static function newFactory(): DigitalBusinessCardEventFactory
    {
        return DigitalBusinessCardEventFactory::new();
    }
}
