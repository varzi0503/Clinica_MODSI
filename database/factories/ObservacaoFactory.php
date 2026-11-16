<?php

namespace Database\Factories;

use App\Models\Consulta;
use App\Models\Observacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Observacao>
 */
class ObservacaoFactory extends Factory
{
    /**
     * Por omissão, numa consulta realizada e escrita pelo profissional dessa consulta.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consulta_id' => Consulta::factory()->realizada(),
            'user_id' => fn (array $atributos): int => Consulta::findOrFail($atributos['consulta_id'])->profissional->user_id,
            'texto' => fake()->sentence(12),
        ];
    }
}
