<?php

namespace Database\Factories;

use App\Models\Paciente;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Paciente>
 */
class PacienteFactory extends Factory
{
    /**
     * Cria também a conta, com o perfil "paciente". O nome da ficha é o da conta (D10).
     * Para uma ficha de balcão, sem conta, usar semConta().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->paciente(),
            'nome' => fn (array $atributos): string => User::find($atributos['user_id'])?->name ?? fake()->name(),
            'num_utente' => fake()->unique()->numerify('#########'),
            'telefone' => null,
            'email_contacto' => null,
        ];
    }

    /** Ficha criada ao balcão: existe sem conta até a receção lhe associar uma (D11). */
    public function semConta(): static
    {
        return $this->state(['user_id' => null]);
    }
}
