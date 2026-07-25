<?php

namespace DigitalCardKit\Laravel\Database\Factories;

use DigitalCardKit\Laravel\Models\DigitalBusinessCardBlock;
use DigitalCardKit\Laravel\Support\Config;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DigitalBusinessCardBlock>
 */
class DigitalBusinessCardBlockFactory extends Factory
{
    protected $model = DigitalBusinessCardBlock::class;

    public function modelName(): string
    {
        return Config::model('block');
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'digital_business_card_id' => DigitalBusinessCardFactory::new(),
            'type' => 'link',
            'title' => $this->faker->sentence(3),
            'url' => $this->faker->url(),
            'sort_order' => 0,
            'is_enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(['is_enabled' => false]);
    }

    /** @param  array<int, string>  $images */
    public function gallery(array $images = []): static
    {
        return $this->state([
            'type' => 'gallery',
            'url' => null,
            'data' => ['gallery' => $images],
        ]);
    }
}
