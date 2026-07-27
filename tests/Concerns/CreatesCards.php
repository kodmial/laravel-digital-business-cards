<?php

namespace DigitalCardKit\Laravel\Tests\Concerns;

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;

/**
 * A fixed fixture on top of the packaged factory.
 *
 * The factory itself produces random data, which is what a host application
 * wants; these tests assert on exact rendered values, so they pin every field
 * the assertions depend on.
 */
trait CreatesCards
{
    /** @param  array<string, mixed>  $attributes */
    protected function createCard(array $attributes = []): DigitalBusinessCard
    {
        /** @var DigitalBusinessCard */
        return DigitalBusinessCard::factory()
            ->state(['is_published' => true])
            ->create(array_merge([
                'slug' => 'example-card',
                'first_name' => 'Alex',
                'last_name' => 'Morgan',
                'middle_name' => 'Taylor',
                'job_title' => 'Product designer',
                'company_name' => 'Example Studio',
                'headline' => 'Building useful digital products',
                'about' => 'Independent product design practice.',
                'contact_methods' => [
                    ['type' => 'phone', 'label' => 'Phone', 'value' => '+1 202 555 0123'],
                    ['type' => 'email', 'label' => 'Email', 'value' => 'alex@example.test'],
                    ['type' => 'telegram', 'label' => 'Telegram', 'value' => '@alex_example'],
                    ['type' => 'whatsapp', 'label' => 'WhatsApp', 'value' => '+12025550123'],
                    ['type' => 'website', 'label' => 'Website', 'value' => 'https://example.test'],
                    ['type' => 'address', 'label' => 'Office', 'value' => 'Example Street 1'],
                ],
                'background_color' => '#0f172a',
                'accent_color' => '#6366f1',
                'text_color' => '#f1f5f9',
                'font_family' => 'system',
                'button_style' => 'rounded',
                'lead_form_enabled' => true,
                'lead_form_title' => 'Share your contact details',
                'lead_notification_emails' => ['owner@example.test'],
                'lead_send_confirmation' => false,
                'lead_consent_required' => true,
                'meta_title' => 'Alex Morgan — digital card',
                'meta_description' => 'Alex Morgan contact details',
            ], $attributes));
    }
}
