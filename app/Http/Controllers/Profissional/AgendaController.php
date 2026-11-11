<?php

namespace App\Http\Controllers\Profissional;

use App\Enums\EstadoConsulta;
use App\Http\Controllers\Controller;
use App\Models\Consulta;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * UC11 — agenda do profissional, por dia ou por semana (issue #62).
 * Mostra as consultas que ocupam vaga (pedidas, confirmadas, realizadas,
 * faltas); as canceladas, rejeitadas e em espera ficam de fora.
 * Cada profissional só vê as suas.
 */
class AgendaController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $profissional = $request->user()->profissional;
        if (! $profissional) {
            return redirect()->route('home')
                ->with('error', __('A sua conta não tem ficha de profissional associada. Contacte a receção.'));
        }

        $dados = $request->validate([
            'data' => ['nullable', 'date_format:Y-m-d'],
            'vista' => ['nullable', 'in:dia,semana'],
        ]);

        $vista = $dados['vista'] ?? 'semana';
        $data = isset($dados['data']) ? CarbonImmutable::parse($dados['data']) : CarbonImmutable::today();

        [$de, $ate] = $vista === 'dia'
            ? [$data->startOfDay(), $data->endOfDay()]
            : [$data->startOfWeek(CarbonImmutable::MONDAY), $data->endOfWeek(CarbonImmutable::SUNDAY)];

        $estados = array_filter(EstadoConsulta::cases(), fn (EstadoConsulta $e) => $e->ocupaVaga());

        $consultas = Consulta::with(['paciente', 'especialidade'])
            ->where('profissional_id', $profissional->id)
            ->whereBetween('inicio', [$de, $ate])
            ->whereIn('estado', $estados)
            ->orderBy('inicio')
            ->get()
            ->groupBy(fn (Consulta $c) => $c->inicio->toDateString());

        return view('profissional.agenda', [
            'vista' => $vista,
            'de' => $de,
            'ate' => $ate,
            // diffInDays() devolve float no Carbon 3 (ex.: 6.99999 numa semana): arredonda-se para baixo.
            'dias' => collect(range(0, (int) $de->diffInDays($ate)))->map(fn (int $i) => $de->addDays($i)),
            'consultas' => $consultas,
            'anterior' => ($vista === 'dia' ? $data->subDay() : $data->subWeek())->toDateString(),
            'seguinte' => ($vista === 'dia' ? $data->addDay() : $data->addWeek())->toDateString(),
            'data' => $data->toDateString(),
        ]);
    }
}
