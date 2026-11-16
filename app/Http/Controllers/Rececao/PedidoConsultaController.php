<?php

namespace App\Http\Controllers\Rececao;

use App\Enums\EstadoConsulta;
use App\Http\Controllers\Controller;
use App\Models\Consulta;
use App\Services\Marcacoes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * UC16 — a receção confirma ou rejeita pedidos de consulta.
 * Rejeitar liberta a vaga e promove a lista de espera (D32).
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

    public function confirmar(Request $request, Consulta $consulta, Marcacoes $marcacoes): RedirectResponse|JsonResponse
    {
        // Se outro utilizador tratou o pedido entretanto (duplo clique, duas rececionistas),
        // o serviço lança TransicaoEstadoInvalida, que é respondida em bootstrap/app.php.
        $marcacoes->confirmar($consulta, $request->user());

        return $this->concluir($request, __('Consulta confirmada.'));
    }

    public function rejeitar(Request $request, Consulta $consulta, Marcacoes $marcacoes): RedirectResponse|JsonResponse
    {
        $dados = $request->validate(['motivo' => ['required', 'string', 'max:255']]);

        // Rejeitar liberta a vaga: o serviço promove a lista de espera na mesma transação (D32).
        $marcacoes->rejeitar($consulta, $request->user(), $dados['motivo']);

        return $this->concluir($request, __('Pedido rejeitado.'));
    }

    private function concluir(Request $request, string $mensagem): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $mensagem])
            : redirect()->route('rececao.pedidos.index')->with('success', $mensagem);
    }
}
