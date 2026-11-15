<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Paciente\PedirConsultaRequest;
use App\Models\Especialidade;
use App\Models\Paciente;
use App\Services\Marcacoes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * UC07 — o paciente pede uma consulta (issue #53).
 * O pedido fica "pedida" e aparece na lista da receção (UC16), que o confirma
 * ou rejeita. O paciente é sempre o da conta autenticada: um paciente_id vindo
 * do formulário é ignorado. Pedir uma vaga já ocupada (lista de espera) é do #75.
 */
class PedidoController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->paciente($request)) {
            return $this->semFicha();
        }

        return view('paciente.pedir', [
            'especialidades' => Especialidade::has('profissionais')->orderBy('nome')->get(),
        ]);
    }

    public function store(PedirConsultaRequest $request, Marcacoes $marcacoes): RedirectResponse
    {
        $profissional = $request->profissional();
        $inicio = $request->inicio();

        $marcacoes->pedir($request->paciente(), $profissional, $request->especialidadeId(), $inicio, $request->motivo(), $request->user());

        return redirect()->route('paciente.pedidos.create')->with('success', __('Pedido enviado para :data com :profissional. A receção vai confirmá-lo.', [
            'data' => $inicio->format('d/m/Y H:i'),
            'profissional' => $profissional->user->name,
        ]));
    }

    private function paciente(Request $request): ?Paciente
    {
        return $request->user()->paciente;
    }

    private function semFicha(): RedirectResponse
    {
        return redirect()->route('home')
            ->with('error', __('A sua conta não tem ficha de paciente associada. Contacte a receção.'));
    }
}
