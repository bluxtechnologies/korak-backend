<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicketMessage>
 */
class SupportTicketMessageFactory extends Factory
{
    /**
     * The model that this factory builds.
     *
     * @var class-string<SupportTicketMessage>
     */
    protected $model = SupportTicketMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => SupportTicket::factory(),
            'author_type' => 'customer',
            'author_id' => 1,
            'author_name' => fake()->name(),
            'body' => fake()->paragraph(),
            'read_at' => null,
            'created_at' => now(),
        ];
    }
}
