<?php

namespace DigitalCardKit\Laravel\Services;

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Support\Config;
use Illuminate\Support\Facades\Storage;
use Sabre\VObject\Component\VCard;

class VCardGenerator
{
    public function generate(DigitalBusinessCard $card): string
    {
        $vcard = new VCard([
            'FN' => $card->full_name,
            'N' => [$card->last_name, $card->first_name, $card->middle_name, '', ''],
            'ORG' => $card->company_name,
            'TITLE' => $card->job_title,
            'NOTE' => $card->headline,
        ]);

        foreach ($card->contact_methods ?: [] as $method) {
            $value = trim((string) ($method['value'] ?? ''));
            if ($value === '') {
                continue;
            }

            match ($method['type'] ?? 'link') {
                'phone' => $vcard->add('TEL', preg_replace('/[^0-9+]/', '', $value), ['type' => ['work', 'voice']]),
                'email' => $vcard->add('EMAIL', $value, ['type' => 'work']),
                'address' => $vcard->add('ADR', ['', '', $value, '', '', '', ''], ['type' => 'work']),
                default => $vcard->add('URL', $value),
            };
        }

        if ($card->avatar) {
            $vcard->add('PHOTO', Storage::disk(Config::disk())->url($card->avatar), ['VALUE' => 'uri']);
        }

        return $vcard->serialize();
    }
}
