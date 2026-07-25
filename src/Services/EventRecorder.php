<?php

namespace DigitalCardKit\Laravel\Services;

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventRecorder
{
    public function record(Request $request, DigitalBusinessCard $card, string $type, ?int $blockId = null): void
    {
        $card->events()->create([
            'type' => $type,
            'digital_business_card_block_id' => $blockId,
            'visitor_hash' => hash('sha256', $request->ip().'|'.$request->userAgent().'|'.config('app.key')),
            'metadata' => [
                'referer' => Str::limit((string) $request->header('Referer'), 500),
                'user_agent' => Str::limit((string) $request->userAgent(), 500),
            ],
            'occurred_at' => now(),
        ]);
    }
}
