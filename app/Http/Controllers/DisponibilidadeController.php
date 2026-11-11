<?php

namespace App\Http\Controllers;

use App\Models\Especialidade;
use App\Models\Profissional;
use App\Services\Disponibilidade;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * UC06 — consultar disponibilidade. Público: não expõe dados de pacientes,
 * só os inícios das vagas livres.
 */
class DisponibilidadeController extends Controller
{
    public const DIAS_POR_OMISSAO = 14;

    public const DIAS_MAXIMO = 31;

    public function index(): View
    {
        return view('disponibilidade', [
            'especialidades' => Especialidade::has('profissionais')->orderBy('nome')->get(),
        ]);
    }

    public function vagas(Request $request, Disponibilidade $disponibilidade): JsonResponse
    {
        $dados = $request->validate([
            'especialidade_id' => ['required_without:profissional_id', 'nullable', 'integer', 'exists:especialidades,id'],
            'profissional_id' => ['required_without:especialidade_id', 'nullable', 'integer', 'exists:profissionais,id'],
            'de' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'dias' => ['nullable', 'integer', 'min:1', 'max:'.self::DIAS_MAXIMO],
        ]);

        $de = isset($dados['de']) ? CarbonImmutable::parse($dados['de']) : CarbonImmutable::today();
        $ate = $de->addDays(($dados['dias'] ?? self::DIAS_POR_OMISSAO) - 1);

        $profissionais = Profissional::with('user')
            ->when($dados['profissional_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->when($dados['especialidade_id'] ?? null, fn ($q, $id) => $q->whereRelation('especialidades', 'especialidades.id', $id))
            ->get()
            ->sortBy(fn (Profissional $p) => $p->user->name)
            ->values();

        return response()->json([
            'de' => $de->toDateString(),
            'ate' => $ate->toDateString(),
            'duracao_minutos' => Disponibilidade::DURACAO_MINUTOS,
            'profissionais' => $profissionais->map(fn (Profissional $p) => [
                'id' => $p->id,
                'nome' => $p->user->name,
                'tipo' => $p->tipo->label(),
                'vagas' => $disponibilidade->vagasLivres($p, $de, $ate)
                    ->map(fn (CarbonImmutable $v) => $v->format('Y-m-d\TH:i'))
                    ->all(),
            ]),
        ]);
    }
}
