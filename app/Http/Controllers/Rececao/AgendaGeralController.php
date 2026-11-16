<?php

namespace App\Http\Controllers\Rececao;

use App\Http\Controllers\Controller;
use App\Models\Profissional;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * UC20 — agenda geral (issue #68): a receção vê, por dia ou por semana, as consultas de todos os
 * profissionais que ocupam vaga. É só de leitura; cancelar e reagendar fazem-se na página Consultas.
 * Lê a vista SQL v_consultas_detalhe (issue #36), que já junta os nomes.
 */
class AgendaGeralController extends Controller
{
    public function index(Request $request): View
    {
        $dados = $request->validate([
            'data' => ['nullable', 'date_format:Y-m-d'],
            'vista' => ['nullable', 'in:dia,semana'],
            'profissional_id' => ['nullable', 'integer', 'exists:profissionais,id'],
        ]);

        $vista = $dados['vista'] ?? 'dia';
        $data = isset($dados['data']) ? CarbonImmutable::createFromFormat('Y-m-d|', $dados['data']) : CarbonImmutable::today();

        [$de, $ate] = $vista === 'dia'
            ? [$data->startOfDay(), $data->endOfDay()]
            : [$data->startOfWeek(CarbonImmutable::MONDAY), $data->endOfWeek(CarbonImmutable::SUNDAY)];

        // Por dia e, dentro de cada dia, por profissional (por ordem de nome) e por hora.
        $consultas = DB::table('v_consultas_detalhe')
            ->whereBetween('inicio', [$de, $ate])
            ->whereNotNull('vaga_ocupada')
            ->when($dados['profissional_id'] ?? null, fn ($q, $id) => $q->where('profissional_id', $id))
            ->orderBy('profissional_nome')
            ->orderBy('profissional_id')
            ->orderBy('inicio')
            ->get()
            ->groupBy([fn (object $c) => substr($c->inicio, 0, 10), 'profissional_id']);

        return view('rececao.agenda', [
            'vista' => $vista,
            'data' => $data->toDateString(),
            'de' => $de,
            'ate' => $ate,
            // diffInDays() devolve float no Carbon 3: arredonda-se para baixo.
            'dias' => collect(range(0, (int) $de->diffInDays($ate)))->map(fn (int $i) => $de->addDays($i)),
            'consultas' => $consultas,
            'profissionais' => Profissional::with('user')->get()->sortBy(fn (Profissional $p) => $p->user->name),
            'profissionalId' => $dados['profissional_id'] ?? null,
            'anterior' => ($vista === 'dia' ? $data->subDay() : $data->subWeek())->toDateString(),
            'seguinte' => ($vista === 'dia' ? $data->addDay() : $data->addWeek())->toDateString(),
        ]);
    }
}
