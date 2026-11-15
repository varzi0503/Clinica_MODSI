<?php

namespace Database\Factories;

use App\Models\Perfil;
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
            'perfil_id' => Perfil::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function administrador(): static
    {
        return $this->comPerfil(Perfil::ADMINISTRADOR);
    }

    public function rececionista(): static
    {
        return $this->comPerfil(Perfil::RECECIONISTA);
    }

    /** Só a conta. Para a conta com a ficha, usar Profissional::factory(). */
    public function profissional(): static
    {
        return $this->comPerfil(Perfil::PROFISSIONAL);
    }

    /** Só a conta. Para a conta com a ficha, usar Paciente::factory(). */
    public function paciente(): static
    {
        return $this->comPerfil(Perfil::PACIENTE);
    }

    /** Usa o perfil fixo com este nome, criando-o se ainda não existir (os perfis têm nome único). */
    private function comPerfil(string $nome): static
    {
        return $this->state(fn (): array => [
            'perfil_id' => Perfil::firstOrCreate(['nome' => $nome])->id,
        ]);
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
}
