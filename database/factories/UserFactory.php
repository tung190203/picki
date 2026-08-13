<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password123'),
            'avatar_url' => $this->faker->imageUrl(200, 200, 'people'),
            'google_id' => null,
            'role' => $this->faker->randomElement(['player', 'referee', 'admin']),
            'email_verified_at' => now(),
            'is_profile_completed' => $this->faker->boolean(80),
            'location_id' => Location::inRandomOrder()->first()?->id,
            'about' => $this->faker->sentence(),
            'remember_token' => Str::random(10),
            'is_guest' => false,
            'is_banned' => false,
            'is_merged' => false,
            'merged_into_user_id' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function merged(int $survivorId): static
    {
        return $this->state(fn (array $attributes) => [
            'is_merged' => true,
            'merged_into_user_id' => $survivorId,
        ]);
    }

    public function banned(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_banned' => true,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }
}
