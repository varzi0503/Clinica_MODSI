<?php

namespace App\Http\Controllers\Rececao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rececao\ReagendarConsultaRequest;
use App\Models\Consulta;
use App\Models\Profissional;
use App\Services\Marcacoes;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * UC18 — a receção vê as consultas marcadas, cancela-as e reagenda-as (issue #67).
 * As regras de quem pode fazer cada ação estão na ConsultaPolicy, aplicada nas rotas.
 */
class ConsultaController extends Controller
{
    public function index(Request $request, Marcacoes $marcacoes): View
    {
        $filtros = $request->validate([
            'profissional_id' => ['nullable', 'integer', 'exists:profissionais,id'],
            'de' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $de = isset($filtros['de']) ? CarbonImmutable::createFromFormat('Y-m-d|', $filtros['de']) : CarbonImmutable::today();

        $consultas = Consulta::with(['paciente', 'profissional.user', 'especialidade'])
            ->where('inicio', '>=', $de)
            ->when($filtros['profissional_id'] ?? null, fn ($q, $id) => $q->where('profissional_id', $id))
            ->orderBy('inicio')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('rececao.consultas.index', [
            'consultas' => $consultas,
            'reagendaveis' => $consultas->getCollection()->filter(fn (Consulta $c) => $marcacoes->podeSerReagendada($c))->modelKeys(),
            'profissionais' => Profissional::with('user')->get()->sortBy(fn (Profissional $p) => $p->user->name),
            'filtros' => $filtros + ['de' => $de->toDateString()],
        ]);
    }

    public function cancelar(Request $request, Consulta $consulta, Marcacoes $marcacoes): RedirectResponse|JsonResponse
    {
        $dados = $request->validate(['motivo' => ['required', 'string', 'max:255']]);

        // Uma consulta já terminada lança TransicaoEstadoInvalida (409, em bootstrap/app.php).
        $marcacoes->cancelar($consulta, $request->user(), $dados['motivo']);

        return $this->responder($request, __('Consulta cancelada.'));
    }

    public function reagendamento(Consulta $consulta, Marcacoes $marcacoes): View|RedirectResponse
    {
        if (! $marcacoes->podeSerReagendada($consulta)) {
            return redirect()->route('rececao.consultas.index')
                ->with('error', __('Só se reagendam pedidos pendentes e consultas confirmadas que ainda não começaram.'));
        }

        return view('rececao.consultas.reagendar', [
            'consulta' => $consulta->load(['paciente', 'profissional.user', 'especialidade']),
        ]);
    }

    public function reagendar(ReagendarConsultaRequest $request, Consulta $consulta, Marcacoes $marcacoes): RedirectResponse
    {
        $profissional = $request->profissional();
        $inicio = $request->inicio();

        $marcacoes->reagendar($consulta, $profissional, $inicio, $request->user());

        return redirect()->route('rececao.consultas.index')->with('success', __('Consulta reagendada para :data com :profissional.', [
            'data' => $inicio->format('d/m/Y H:i'),
            'profissional' => $profissional->user->name,
        ]));
    }
}
