<?php

namespace DigitalCardKit\Laravel\Services;

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class EventRecorder
{
    public function record(Request $request, DigitalBusinessCard $card, string $type, ?int $blockId = null): void
    {
        $card->events()->create([
            'type' => $type,
            'digital_business_card_block_id' => $blockId,
            'visitor_hash' => $this->visitorHash($request),
            'metadata' => [
                'referer' => Str::limit((string) $request->header('Referer'), 500),
                'user_agent' => Str::limit((string) $request->userAgent(), 500),
            ],
            'occurred_at' => now(),
        ]);
    }

    /**
     * Pseudonymise the visitor. The application key is used as an HMAC key
     * rather than being concatenated into the hashed payload, so the digest
     * cannot be reproduced without it.
     */
    protected function visitorHash(Request $request): string
    {
        return hash_hmac(
            'sha256',
            (string) $request->ip().'|'.(string) $request->userAgent(),
            $this->applicationKey(),
        );
    }

    private function applicationKey(): string
    {
        return Crypt::getKey();
    }
}
