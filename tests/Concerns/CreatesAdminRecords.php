<?php

namespace DigitalCardKit\Laravel\Tests\Concerns;

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardLead;
use DigitalCardKit\Laravel\Tests\Fixtures\User;

trait CreatesAdminRecords
{
    protected function createAdminUser(): User
    {
        return User::query()->create([
            'name' => 'Package administrator',
            'email' => 'admin@example.test',
            'password' => bcrypt('password'),
        ]);
    }

    protected function createAdminCard(array $attributes = []): DigitalBusinessCard
    {
        return DigitalBusinessCard::query()->create(array_merge([
            'slug' => 'admin-check',
            'first_name' => 'Alex',
            'last_name' => 'Morgan',
            'is_published' => true,
            'contact_methods' => [
                ['type' => 'phone', 'label' => 'Phone', 'value' => '+1 202 555 0123'],
            ],
        ], $attributes));
    }

    protected function createAdminLead(DigitalBusinessCard $card, array $attributes = []): DigitalBusinessCardLead
    {
        return $card->leads()->create(array_merge([
            'name' => 'Taylor Smith',
            'phone' => '+1 202 555 0199',
            'email' => 'taylor@example.test',
            'company' => 'Example Company',
            'comment' => 'Please contact me',
            'consent_given' => true,
            'submitted_at' => now(),
        ], $attributes));
    }
}
