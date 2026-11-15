<?php

namespace App\Http\Controllers\Rececao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rececao\MarcarConsultaRequest;
use App\Models\Especialidade;
use App\Models\Paciente;
use App\Services\Marcacoes;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * UC17 — a receção marca uma consulta em nome do paciente (issue #66).
 * A consulta nasce "pedida" e passa logo a "confirmada" na mesma transação:
 * a matriz de transições mantém-se e o histórico regista os dois passos.
 */
class MarcacaoController extends Controller
{
    public function create(): View
    {
        return view('rececao.marcar', [
            'pacientes' => Paciente::orderBy('nome')->get(),
            'especialidades' => Especialidade::has('profissionais')->orderBy('nome')->get(),
        ]);
    }

    public function store(MarcarConsultaRequest $request, Marcacoes $marcacoes): RedirectResponse
    {
        $profissional = $request->profissional();
        $inicio = $request->inicio();

        $marcacoes->marcarPelaRececao($request->paciente(), $profissional, $request->especialidadeId(), $inicio, $request->motivo(), $request->user());

        return redirect()->route('rececao.consultas.create')->with('success', __('Consulta marcada para :data com :profissional.', [
            'data' => $inicio->format('d/m/Y H:i'),
            'profissional' => $profissional->user->name,
        ]));
    }
}
