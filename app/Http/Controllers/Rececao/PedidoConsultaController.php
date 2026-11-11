<?php

namespace App\Http\Controllers\Rececao;

use App\Enums\EstadoConsulta;
use App\Exceptions\TransicaoEstadoInvalida;
use App\Http\Controllers\Controller;
use App\Models\Consulta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * UC16 — a receção confirma ou rejeita pedidos de consulta.
 * A promoção da lista de espera quando uma vaga se liberta é do âmbito da issue #75.
 */
class PedidoConsultaController extends Controller
{
    public function index(): View
    {
        $pedidos = Consulta::with(['paciente', 'profissional.user', 'especialidade'])
            ->where('estado', EstadoConsulta::Pedida)
            ->orderBy('data_pedido')
            ->orderBy('id')
            ->get();

        return view('rececao.pedidos', ['pedidos' => $pedidos]);
    }

    public function confirmar(Request $request, Consulta $consulta): RedirectResponse|JsonResponse
    {
        return $this->transitar($request, $consulta, EstadoConsulta::Confirmada, null,
            __('Consulta confirmada.'));
    }

    public function rejeitar(Request $request, Consulta $consulta): RedirectResponse|JsonResponse
    {
        $dados = $request->validate(['motivo' => ['required', 'string', 'max:255']]);

        return $this->transitar($request, $consulta, EstadoConsulta::Rejeitada, $dados['motivo'],
            __('Pedido rejeitado.'));
    }

    private function transitar(Request $request, Consulta $consulta, EstadoConsulta $novo, ?string $motivo, string $mensagem): RedirectResponse|JsonResponse
    {
        try {
            $consulta->mudarEstado($novo, $request->user(), $motivo);
        } catch (TransicaoEstadoInvalida) {
            // Outro utilizador tratou o pedido entretanto (duplo clique, duas rececionistas).
            $aviso = __('Este pedido já não está pendente.');

            return $request->expectsJson()
                ? response()->json(['message' => $aviso], 409)
                : redirect()->route('rececao.pedidos.index')->with('error', $aviso);
        }

        return $request->expectsJson()
            ? response()->json(['message' => $mensagem])
            : redirect()->route('rececao.pedidos.index')->with('success', $mensagem);
    }
}
