<?php

namespace App\Http\Controllers\Rececao;

use App\Enums\EstadoConsulta;
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

        $consulta = $marcacoes->marcarPelaRececao($request->paciente(), $profissional, $request->especialidadeId(), $inicio, $request->motivo(), $request->user(), $request->aceitaEspera());

        $texto = $consulta->estado === EstadoConsulta::EmEspera
            ? 'Paciente colocado na lista de espera para :data com :profissional.'
            : 'Consulta marcada para :data com :profissional.';

        return redirect()->route('rececao.consultas.create')->with('success', __($texto, [
            'data' => $inicio->format('d/m/Y H:i'),
            'profissional' => $profissional->user->name,
        ]));
    }
}
