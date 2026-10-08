<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Usado apenas em testes. Senhas são aleatórias: não há senha padrão no código.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Str::random(32),
            'role' => User::COLLABORATOR,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function master(): static
    {
        return $this->state(fn () => ['role' => User::MASTER]);
    }

    public function editor(): static
    {
        return $this->state(fn () => ['role' => User::EDITOR]);
    }
}
