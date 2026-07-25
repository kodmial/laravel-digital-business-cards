<?php

namespace DigitalCardKit\Laravel\Database\Factories;

use DigitalCardKit\Laravel\Models\DigitalBusinessCardEvent;
use DigitalCardKit\Laravel\Support\Config;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DigitalBusinessCardEvent>
 */
class DigitalBusinessCardEventFactory extends Factory
{
    protected $model = DigitalBusinessCardEvent::class;

    public function modelName(): string
    {
        return Config::model('event');
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'digital_business_card_id' => DigitalBusinessCardFactory::new(),
            'type' => 'view',
            'visitor_hash' => hash('sha256', $this->faker->uuid()),
            'occurred_at' => now(),
        ];
    }

    public function ofType(string $type): static
    {
        return $this->state(['type' => $type]);
    }
}
