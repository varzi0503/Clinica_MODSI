<?php

namespace App\Http\Requests\Rececao;

use App\Enums\TipoProfissional;
use App\Models\Especialidade;
use App\Models\Profissional;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Dados para a receção editar a ficha de um profissional (issue #70).
 * O email e a palavra-passe são da conta e não se mudam aqui.
 */
class AtualizarProfissionalRequest extends FormRequest
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
            'tipo' => ['required', Rule::enum(TipoProfissional::class)],
            // A cédula é única dentro de cada Ordem (D22); a própria ficha não conta.
            'num_cedula' => [
                'required', 'string', 'max:20',
                Rule::unique('profissionais', 'num_cedula')
                    ->where('tipo', $this->input('tipo'))
                    ->ignore($this->profissional()->id),
            ],
            'contacto' => ['nullable', 'string', 'max:20'],
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

    /**
     * Uma especialidade em que o profissional já tem consultas não se retira: as consultas
     * referenciam o par profissional/especialidade e a base de dados recusaria a remoção.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('especialidades') || $validator->errors()->has('especialidades.*')) {
                    return;
                }

                $profissional = $this->profissional();
                $retiradas = $profissional->especialidades()->pluck('especialidades.id')
                    ->diff(array_map('intval', $this->input('especialidades', [])));

                $comConsultas = $profissional->consultas()
                    ->whereIn('especialidade_id', $retiradas)
                    ->distinct()
                    ->pluck('especialidade_id');

                if ($comConsultas->isNotEmpty()) {
                    $validator->errors()->add('especialidades', __('Não pode retirar :nomes: o profissional tem consultas nessa especialidade.', [
                        'nomes' => Especialidade::whereIn('id', $comConsultas)->orderBy('nome')->pluck('nome')->join(', '),
                    ]));
                }
            },
        ];
    }

    private function profissional(): Profissional
    {
        return $this->route('profissional');
    }
}
