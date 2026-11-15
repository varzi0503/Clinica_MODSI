<?php

namespace Database\Factories;

use App\Enums\TipoProfissional;
use App\Models\Especialidade;
use App\Models\Profissional;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profissional>
 */
class ProfissionalFactory extends Factory
{
    /**
     * Cria também a conta, com o perfil "profissional". Por omissão é médico e não tem
     * especialidades nem horário: usar comEspecialidade() e comHorario().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->profissional(),
            'tipo' => TipoProfissional::Medico,
            'num_cedula' => fake()->unique()->numerify('#####'),
            'contacto' => null,
        ];
    }

    public function medico(): static
    {
        return $this->state(['tipo' => TipoProfissional::Medico]);
    }

    public function enfermeiro(): static
    {
        return $this->state(['tipo' => TipoProfissional::Enfermeiro]);
    }

    /** Associa a especialidade dada ou, sem argumento, uma nova. Pode ser chamado várias vezes. */
    public function comEspecialidade(?Especialidade $especialidade = null): static
    {
        return $this->afterCreating(function (Profissional $profissional) use ($especialidade): void {
            $profissional->especialidades()->attach(($especialidade ?? Especialidade::factory()->create())->id);
        });
    }

    /**
     * Acrescenta um período de atendimento semanal. Pode ser chamado várias vezes.
     *
     * @param  int  $diaSemana  1 = segunda ... 7 = domingo
     * @param  string  $horaInicio  hora certa ou meia, por exemplo '09:00'
     * @param  string  $horaFim  hora certa ou meia, por exemplo '13:00'
     */
    public function comHorario(int $diaSemana, string $horaInicio, string $horaFim): static
    {
        return $this->afterCreating(function (Profissional $profissional) use ($diaSemana, $horaInicio, $horaFim): void {
            $profissional->horarios()->create([
                'dia_semana' => $diaSemana,
                'hora_inicio' => $horaInicio,
                'hora_fim' => $horaFim,
            ]);
        });
    }
}
