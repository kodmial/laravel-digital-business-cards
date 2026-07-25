<?php

namespace DigitalCardKit\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalBusinessCardEvent extends Model
{
    protected $table = 'digital_business_card_events';

    public $timestamps = false;

    protected $fillable = ['type', 'digital_business_card_block_id', 'visitor_hash', 'metadata', 'occurred_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'datetime'];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(config('digital-business-cards.models.card'), 'digital_business_card_id');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(config('digital-business-cards.models.block'), 'digital_business_card_block_id');
    }
}
