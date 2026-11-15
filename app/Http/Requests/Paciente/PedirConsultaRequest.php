<?php

namespace App\Http\Requests\Paciente;

use App\Http\Requests\Concerns\DadosDaMarcacao;
use App\Models\Paciente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Pedido de consulta feito pelo paciente (issue #53).
 * O paciente é sempre o da conta autenticada: um paciente_id vindo do formulário é ignorado.
 */
class PedirConsultaRequest extends FormRequest
{
    use DadosDaMarcacao;

    /** O perfil é verificado pelo middleware perfil:paciente; aqui exige-se a ficha. */
    public function authorize(): bool
    {
        return $this->user()->paciente !== null;
    }

    /** Uma conta de paciente sem ficha não recebe um 403: é encaminhada para a receção. */
    protected function failedAuthorization(): never
    {
        throw new HttpResponseException(
            redirect()->route('home')->with('error', __('A sua conta não tem ficha de paciente associada. Contacte a receção.'))
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return $this->regrasDaMarcacao();
    }

    public function paciente(): Paciente
    {
        return $this->user()->paciente;
    }
}
