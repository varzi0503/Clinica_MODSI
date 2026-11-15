<?php

namespace App\Http\Requests\Rececao;

use App\Enums\TipoProfissional;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Dados para a receção criar um profissional: conta, ficha e especialidades de uma vez (issue #70). */
class CriarProfissionalRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'tipo' => ['required', Rule::enum(TipoProfissional::class)],
            // A cédula é única dentro de cada Ordem: médicos e enfermeiros numeram à parte (D22).
            'num_cedula' => [
                'required', 'string', 'max:20',
                Rule::unique('profissionais', 'num_cedula')->where('tipo', $this->input('tipo')),
            ],
            'contacto' => ['nullable', 'string', 'max:20'],
            // Sem especialidade o profissional não pode ter consultas (FK composta em consultas).
            'especialidades' => ['required', 'array', 'min:1'],
            'especialidades.*' => ['integer', 'distinct', 'exists:especialidades,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'num_cedula.unique' => __('Já existe um profissional deste tipo com este nº de cédula.'),
            'especialidades.required' => __('Escolha pelo menos uma especialidade.'),
        ];
    }
}
