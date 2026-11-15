<?php

namespace App\Http\Requests\Concerns;

use App\Models\Profissional;
use Carbon\CarbonImmutable;

/**
 * Campos comuns a pedir uma consulta (paciente) e a marcá-la (receção).
 * Se a vaga está livre e se o profissional exerce a especialidade é com o serviço Marcacoes.
 */
trait DadosDaMarcacao
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function regrasDaMarcacao(): array
    {
        return [
            'especialidade_id' => ['required', 'integer', 'exists:especialidades,id'],
            'profissional_id' => ['required', 'integer', 'exists:profissionais,id'],
            'inicio' => ['required', 'date_format:Y-m-d\TH:i'],
            'motivo_consulta' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function profissional(): Profissional
    {
        return Profissional::findOrFail($this->validated('profissional_id'));
    }

    public function especialidadeId(): int
    {
        return (int) $this->validated('especialidade_id');
    }

    /** O '|' põe os segundos a zero (sem ele, o PHP preenche-os com a hora atual). */
    public function inicio(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d\TH:i|', $this->validated('inicio'));
    }

    public function motivo(): ?string
    {
        return $this->validated('motivo_consulta');
    }
}
