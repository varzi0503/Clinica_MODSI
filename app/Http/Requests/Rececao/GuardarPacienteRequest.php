<?php

namespace App\Http\Requests\Rececao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Dados de uma ficha de paciente, ao criar ao balcão e ao editar (issue #58). */
class GuardarPacienteRequest extends FormRequest
{
    /** O perfil é verificado pelo middleware perfil:rececionista do grupo de rotas. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            // Ao editar, a própria ficha não conta para a unicidade (D22).
            'num_utente' => ['required', 'digits:9', Rule::unique('pacientes', 'num_utente')->ignore($this->route('paciente'))],
            'telefone' => ['nullable', 'string', 'max:20'],
            'email_contacto' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'num_utente.unique' => __('Já existe uma ficha com este nº de utente.'),
        ];
    }
}
