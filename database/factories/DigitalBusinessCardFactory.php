<?php

namespace DigitalCardKit\Laravel\Database\Factories;

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Support\Config;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DigitalBusinessCard>
 */
class DigitalBusinessCardFactory extends Factory
{
    protected $model = DigitalBusinessCard::class;

    public function modelName(): string
    {
        return Config::model('card');
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $firstName = $this->faker->firstName();
        $lastName = $this->faker->lastName();

        return [
            'slug' => Str::slug($firstName.' '.$lastName.' '.$this->faker->unique()->numberBetween(1, 99999)),
            'is_published' => false,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'job_title' => $this->faker->jobTitle(),
            'company_name' => $this->faker->company(),
            'headline' => $this->faker->sentence(),
            'contact_methods' => [
                ['type' => 'phone', 'label' => 'Phone', 'value' => '+1 202 555 0123'],
                ['type' => 'email', 'label' => 'Email', 'value' => $this->faker->safeEmail()],
            ],
            'lead_form_enabled' => true,
            'lead_consent_required' => true,
        ];
    }

    public function published(): static
    {
        return $this->state(['is_published' => true]);
    }

    public function draft(): static
    {
        return $this->state(['is_published' => false]);
    }

    public function withoutLeadForm(): static
    {
        return $this->state(['lead_form_enabled' => false]);
    }
}
