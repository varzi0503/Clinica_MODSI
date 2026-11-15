<?php

namespace App\Http\Requests\Rececao;

use App\Http\Requests\Concerns\DadosDaMarcacao;
use App\Models\Paciente;
use Illuminate\Foundation\Http\FormRequest;

/** Consulta marcada pela receção em nome de um paciente (issue #66). */
class MarcarConsultaRequest extends FormRequest
{
    use DadosDaMarcacao;

    /** O perfil é verificado pelo middleware perfil:rececionista do grupo de rotas. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['paciente_id' => ['required', 'integer', 'exists:pacientes,id']] + $this->regrasDaMarcacao();
    }

    public function paciente(): Paciente
    {
        return Paciente::findOrFail($this->validated('paciente_id'));
    }
}
