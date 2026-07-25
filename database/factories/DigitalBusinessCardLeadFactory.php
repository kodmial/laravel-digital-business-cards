<?php

namespace DigitalCardKit\Laravel\Database\Factories;

use DigitalCardKit\Laravel\Models\DigitalBusinessCardLead;
use DigitalCardKit\Laravel\Support\Config;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DigitalBusinessCardLead>
 */
class DigitalBusinessCardLeadFactory extends Factory
{
    protected $model = DigitalBusinessCardLead::class;

    public function modelName(): string
    {
        return Config::model('lead');
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'digital_business_card_id' => DigitalBusinessCardFactory::new(),
            'name' => $this->faker->name(),
            'phone' => '+1 202 555 0199',
            'email' => $this->faker->safeEmail(),
            'company' => $this->faker->company(),
            'comment' => $this->faker->sentence(),
            'consent_given' => true,
            'submitted_at' => now(),
        ];
    }

    public function withoutConsent(): static
    {
        return $this->state(['consent_given' => false]);
    }
}
