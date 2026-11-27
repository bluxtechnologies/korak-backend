<?php

namespace Database\Factories;

use App\Models\WebsiteService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteService>
 */
class WebsiteServiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<WebsiteService>
     */
    protected $model = WebsiteService::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'code' => str()->slug($name, '_'),
            'category' => fake()->randomElement(['telecom', 'utilities', 'entertainment', 'education']),
            'description' => fake()->sentence(),
            'icon' => 'ti ti-apps',
            'is_active' => true,
            'unavailable_headline' => 'Service Unavailable',
            'maintenance_message' => 'This service is currently undergoing scheduled maintenance. Please check back shortly.',
            'sort_order' => fake()->numberBetween(1, 20),
            'metadata' => ['provider' => 'finitypay'],
        ];
    }

    /**
     * Indicate that the service is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
