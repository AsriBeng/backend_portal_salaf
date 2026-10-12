<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
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
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::Admin,
            'gender' => null,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => Role::Admin, 'gender' => null]);
    }

    public function pengurus(Role $role, Gender $gender): static
    {
        return $this->state(['role' => $role, 'gender' => $gender]);
    }

    public function santri(Gender $gender = Gender::Putra): static
    {
        return $this->state(['role' => Role::Santri, 'gender' => $gender]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
