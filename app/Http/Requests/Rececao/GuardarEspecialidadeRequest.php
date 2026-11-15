<?php

namespace App\Http\Requests\Rececao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Dados de uma especialidade, ao criar e ao editar (issue #71). */
class GuardarEspecialidadeRequest extends FormRequest
{
    /** O perfil é verificado pelo middleware perfil:rececionista do grupo de rotas. */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // "Cardiologia " e "Cardiologia" são a mesma especialidade.
        $this->merge(['nome' => is_string($this->nome) ? trim($this->nome) : $this->nome]);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            // Ao editar, o próprio registo não conta para a unicidade.
            'nome' => ['required', 'string', 'max:100', Rule::unique('especialidades', 'nome')->ignore($this->route('especialidade'))],
            'descricao' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.unique' => __('Já existe uma especialidade com este nome.'),
        ];
    }
}
