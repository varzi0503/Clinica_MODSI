<?php

namespace App\Http\Controllers\Profissional;

use App\Http\Controllers\Controller;
use App\Models\Consulta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ficha de uma consulta para o seu profissional: dados, observações e documentos (issue #76).
 * A posse é verificada nas rotas (ConsultaPolicy::verObservacoes).
 */
class ConsultaController extends Controller
{
    public function show(Consulta $consulta): View
    {
        return view('profissional.consulta', [
            'consulta' => $consulta->load(['paciente', 'especialidade', 'historico', 'observacoes.autor', 'documentos.autor']),
        ]);
    }

    public function observar(Request $request, Consulta $consulta): RedirectResponse
    {
        $dados = $request->validate(['texto' => ['required', 'string', 'max:5000']]);

        if (! $consulta->aceitaObservacoes()) {
            return back()->with('error', __('Só se escrevem observações em consultas confirmadas ou realizadas.'));
        }

        $consulta->observacoes()->create(['user_id' => $request->user()->id, 'texto' => $dados['texto']]);

        return back()->with('success', __('Observação guardada.'));
    }
}
