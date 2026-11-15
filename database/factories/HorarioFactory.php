<?php

namespace Database\Factories;

use App\Models\Horario;
use App\Models\Profissional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Horario>
 */
class HorarioFactory extends Factory
{
    /**
     * Segunda-feira, das 09:00 às 13:00. dia_semana: 1 = segunda ... 7 = domingo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profissional_id' => Profissional::factory(),
            'dia_semana' => 1,
            'hora_inicio' => '09:00',
            'hora_fim' => '13:00',
        ];
    }
}
