<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Models\Consulta;
use App\Services\Marcacoes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "As minhas consultas": o paciente vê as suas consultas futuras e cancela-as (UC08, issue #54).
 * O histórico completo, com as consultas passadas, é da issue #55.
 */
class ConsultaController extends Controller
{
    public function index(Request $request, Marcacoes $marcacoes): View|RedirectResponse
    {
        $paciente = $request->user()->paciente;
        if (! $paciente) {
            return redirect()->route('home')
                ->with('error', __('A sua conta não tem ficha de paciente associada. Contacte a receção.'));
        }

        $consultas = $paciente->consultas()
            ->with(['profissional.user', 'especialidade'])
            ->where('inicio', '>=', now())
            ->orderBy('inicio')
            ->get();

        return view('paciente.consultas', [
            'consultas' => $consultas,
            'cancelaveis' => $consultas->filter(fn (Consulta $c) => $marcacoes->pacientePodeCancelar($c))->modelKeys(),
            'horas' => Marcacoes::HORAS_ANTECEDENCIA_PACIENTE,
        ]);
    }

    /** A posse é verificada na rota (ConsultaPolicy::cancelar); aqui, o prazo. */
    public function cancelar(Request $request, Consulta $consulta, Marcacoes $marcacoes): RedirectResponse|JsonResponse
    {
        $dados = $request->validate(['motivo' => ['nullable', 'string', 'max:255']]);

        if ($consulta->estado->transicoesPermitidas() !== [] && ! $marcacoes->pacientePodeCancelar($consulta)) {
            return $this->responder($request, __('Já não é possível cancelar: faltam menos de :horas horas para a consulta. Contacte a receção.', [
                'horas' => Marcacoes::HORAS_ANTECEDENCIA_PACIENTE,
            ]), 422);
        }

        // Uma consulta já realizada, cancelada ou rejeitada lança TransicaoEstadoInvalida (409, em bootstrap/app.php).
        $marcacoes->cancelar($consulta, $request->user(), $dados['motivo'] ?? null);

        return $this->responder($request, __('Consulta cancelada.'));
    }
}
