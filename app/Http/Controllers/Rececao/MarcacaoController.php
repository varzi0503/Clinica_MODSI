<?php

namespace App\Http\Controllers\Rececao;

use App\Enums\EstadoConsulta;
use App\Http\Controllers\Controller;
use App\Models\Consulta;
use App\Models\Especialidade;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Services\Disponibilidade;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

    public function store(Request $request, Disponibilidade $disponibilidade): RedirectResponse
    {
        $dados = $request->validate([
            'paciente_id' => ['required', 'integer', 'exists:pacientes,id'],
            'especialidade_id' => ['required', 'integer', 'exists:especialidades,id'],
            'profissional_id' => ['required', 'integer', 'exists:profissionais,id'],
            'inicio' => ['required', 'date_format:Y-m-d\TH:i'],
            'motivo_consulta' => ['nullable', 'string', 'max:1000'],
        ]);

        $profissional = Profissional::findOrFail($dados['profissional_id']);

        if (! $profissional->especialidades()->whereKey($dados['especialidade_id'])->exists()) {
            throw ValidationException::withMessages([
                'especialidade_id' => __('O profissional escolhido não exerce esta especialidade.'),
            ]);
        }

        // O '|' põe os segundos a zero (sem ele, o PHP preenche-os com a hora atual).
        $inicio = CarbonImmutable::createFromFormat('Y-m-d\TH:i|', $dados['inicio']);

        $livre = $disponibilidade->vagasLivres($profissional, $inicio, $inicio)
            ->contains(fn (CarbonImmutable $vaga) => $vaga->eq($inicio));

        if (! $livre) {
            throw ValidationException::withMessages([
                'inicio' => __('Esta vaga já não está disponível.'),
            ]);
        }

        try {
            DB::transaction(function () use ($dados, $inicio, $request) {
                $consulta = Consulta::registarPedido([
                    'paciente_id' => $dados['paciente_id'],
                    'profissional_id' => $dados['profissional_id'],
                    'especialidade_id' => $dados['especialidade_id'],
                    'inicio' => $inicio,
                    'motivo_consulta' => $dados['motivo_consulta'] ?? null,
                ], EstadoConsulta::Pedida, $request->user());

                $consulta->mudarEstado(EstadoConsulta::Confirmada, $request->user());
            });
        } catch (UniqueConstraintViolationException) {
            // A vaga foi ocupada entre a verificação e a gravação, ou o paciente
            // já tem uma consulta a essa hora com outro profissional.
            throw ValidationException::withMessages([
                'inicio' => __('O paciente já tem uma consulta a essa hora, ou a vaga acabou de ser ocupada.'),
            ]);
        }

        return redirect()->route('rececao.consultas.create')->with('success', __('Consulta marcada para :data com :profissional.', [
            'data' => $inicio->format('d/m/Y H:i'),
            'profissional' => $profissional->user->name,
        ]));
    }
}
