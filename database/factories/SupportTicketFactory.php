<?php

namespace Database\Factories;

use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\SupportTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    /**
     * The model that this factory builds.
     *
     * @var class-string<SupportTicket>
     */
    protected $model = SupportTicket::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'subject' => fake()->sentence(4),
            'category' => fake()->randomElement(TicketCategory::cases()),
            'status' => TicketStatus::OPEN,
            'has_unread_reply' => false,
        ];
    }
}
