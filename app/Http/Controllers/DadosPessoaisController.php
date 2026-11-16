<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * UC10 — "Dados Pessoais" (issue #56). Qualquer conta muda a sua palavra-passe; o paciente
 * com ficha edita também o nome e os contactos. O email de login e o nº de utente não se
 * mudam aqui: identificam a conta e a ficha, e alterá-los é com a receção.
 */
class DadosPessoaisController extends Controller
{
    public function edit(Request $request): View
    {
        return view('dados-pessoais', [
            'user' => $request->user(),
            'paciente' => $request->user()->paciente,
        ]);
    }

    /** Só para pacientes com ficha: cada conta mexe apenas na sua própria ficha. */
    public function update(Request $request): RedirectResponse
    {
        $paciente = $request->user()->paciente;
        abort_if($paciente === null, 403);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'email_contacto' => ['nullable', 'string', 'email', 'max:255'],
        ]);

        // O nome existe na conta e na ficha: muda nos dois sítios ou em nenhum (D10).
        DB::transaction(function () use ($request, $paciente, $dados): void {
            $paciente->update($dados);
            $request->user()->update(['name' => $dados['nome']]);
        });

        return redirect()->route('dados-pessoais.edit')->with('success', __('Dados atualizados.'));
    }

    public function atualizarPalavraPasse(Request $request): RedirectResponse
    {
        $dados = $request->validateWithBag('palavraPasse', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)],
        ]);

        $request->user()->update(['password' => $dados['password']]); // o cast 'hashed' do modelo faz o hash

        return redirect()->route('dados-pessoais.edit')->with('success', __('Palavra-passe alterada.'));
    }
}
