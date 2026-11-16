<?php

namespace App\Http\Requests\Rececao;

use App\Models\Profissional;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/** Nova vaga para uma consulta que a receção reagenda (issue #67). A especialidade é a da consulta. */
class ReagendarConsultaRequest extends FormRequest
{
    /** A posse é verificada na rota (ConsultaPolicy::reagendar). */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'profissional_id' => ['required', 'integer', 'exists:profissionais,id'],
            'inicio' => ['required', 'date_format:Y-m-d\TH:i'],
        ];
    }

    public function profissional(): Profissional
    {
        return Profissional::findOrFail($this->validated('profissional_id'));
    }

    /** O '|' põe os segundos a zero (sem ele, o PHP preenche-os com a hora atual). */
    public function inicio(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d\TH:i|', $this->validated('inicio'));
    }
}
