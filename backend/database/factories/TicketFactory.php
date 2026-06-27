<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'subject' => fake()->sentence(6),
            'description' => fake()->paragraphs(2, true),
            'status' => fake()->randomElement(['open', 'pending', 'resolved', 'closed']),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'requester_id' => User::factory(),
            'assignee_id' => null,
        ];
    }
}
