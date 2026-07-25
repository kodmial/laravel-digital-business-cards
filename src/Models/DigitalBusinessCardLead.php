<?php

namespace DigitalCardKit\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalBusinessCardLead extends Model
{
    protected $table = 'digital_business_card_leads';

    protected $fillable = ['name', 'phone', 'email', 'company', 'comment', 'custom_data', 'consent_given', 'source', 'submitted_at'];

    protected function casts(): array
    {
        return ['custom_data' => 'array', 'consent_given' => 'boolean', 'submitted_at' => 'datetime'];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(config('digital-business-cards.models.card'), 'digital_business_card_id');
    }
}
