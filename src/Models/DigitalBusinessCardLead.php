<?php

namespace DigitalCardKit\Laravel\Models;

use DigitalCardKit\Laravel\Database\Factories\DigitalBusinessCardLeadFactory;
use DigitalCardKit\Laravel\Support\Config;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalBusinessCardLead extends Model
{
    use HasFactory;

    protected $table = 'digital_business_card_leads';

    protected $fillable = ['name', 'phone', 'email', 'company', 'comment', 'custom_data', 'consent_given', 'source', 'submitted_at'];

    protected function casts(): array
    {
        return ['custom_data' => 'array', 'consent_given' => 'boolean', 'submitted_at' => 'datetime'];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Config::model('card'), 'digital_business_card_id');
    }

    protected static function newFactory(): DigitalBusinessCardLeadFactory
    {
        return DigitalBusinessCardLeadFactory::new();
    }
}
