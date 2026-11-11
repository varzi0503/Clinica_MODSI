<?php

namespace App\Http\Controllers\Profissional;

use App\Http\Controllers\Controller;
use App\Models\Consulta;
use App\Models\Horario;
use App\Models\Profissional;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * UC12 — o profissional gere o seu horário semanal (issue #60).
 * Períodos que se repetem todas as semanas, em blocos de 30 min. Não se
 * sobrepõem (verificado aqui: o MySQL não o consegue exprimir num CHECK) e
 * não se removem enquanto tiverem consultas futuras a ocupar vagas.
 */
class HorarioController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $profissional = $this->profissional($request);
        if (! $profissional) {
            return $this->semFicha();
        }

        return view('profissional.horario', [
            'horarios' => $profissional->horarios()->orderBy('dia_semana')->orderBy('hora_inicio')->get()->groupBy('dia_semana'),
            'dias' => self::dias(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $profissional = $this->profissional($request);
        if (! $profissional) {
            return $this->semFicha();
        }

        $meiaHora = ['required', 'date_format:H:i', 'regex:/^\d{2}:(00|30)$/'];
        $dados = $request->validate([
            'dia_semana' => ['required', 'integer', 'between:1,7'],
            'hora_inicio' => $meiaHora,
            'hora_fim' => [...$meiaHora, 'after:hora_inicio'],
        ], [
            'hora_inicio.regex' => __('As horas têm de ser certas ou meias (ex.: 09:00 ou 09:30).'),
            'hora_fim.regex' => __('As horas têm de ser certas ou meias (ex.: 09:00 ou 09:30).'),
        ]);

        $inicio = $dados['hora_inicio'].':00';
        $fim = $dados['hora_fim'].':00';

        $sobrepoe = $profissional->horarios()
            ->where('dia_semana', $dados['dia_semana'])
            ->where('hora_inicio', '<', $fim)
            ->where('hora_fim', '>', $inicio)
            ->exists();

        if ($sobrepoe) {
            throw ValidationException::withMessages([
                'hora_inicio' => __('Este período sobrepõe-se a outro já definido nesse dia.'),
            ]);
        }

        $profissional->horarios()->create([
            'dia_semana' => $dados['dia_semana'],
            'hora_inicio' => $inicio,
            'hora_fim' => $fim,
        ]);

        return redirect()->route('profissional.horario.index')->with('success', __('Período adicionado.'));
    }

    public function destroy(Request $request, Horario $horario): RedirectResponse
    {
        $profissional = $this->profissional($request);
        abort_unless($profissional && $horario->profissional_id === $profissional->id, 403);

        $consultasNoPeriodo = Consulta::where('profissional_id', $profissional->id)
            ->where('inicio', '>', CarbonImmutable::now())
            ->whereNotNull('vaga_ocupada')
            ->pluck('inicio')
            ->map(fn ($i) => CarbonImmutable::parse($i))
            ->filter(fn (CarbonImmutable $i) => $i->isoWeekday() === $horario->dia_semana
                && $i->format('H:i:s') >= $horario->hora_inicio
                && $i->format('H:i:s') < $horario->hora_fim);

        if ($consultasNoPeriodo->isNotEmpty()) {
            // Sem trans_choice(): sem a chave no pt.json, ele usa o idioma de fallback (en)
            // e mostrava a mensagem em inglês a quem tem a interface em português.
            $n = $consultasNoPeriodo->count();
            $mensagem = $n === 1
                ? __('Não é possível remover: há 1 consulta futura neste período.')
                : __('Não é possível remover: há :n consultas futuras neste período.', ['n' => $n]);

            return redirect()->route('profissional.horario.index')->with('error', $mensagem);
        }

        $horario->delete();

        return redirect()->route('profissional.horario.index')->with('success', __('Período removido.'));
    }

    /** @return array<int, string> */
    public static function dias(): array
    {
        return [
            1 => __('Segunda-feira'), 2 => __('Terça-feira'), 3 => __('Quarta-feira'),
            4 => __('Quinta-feira'), 5 => __('Sexta-feira'), 6 => __('Sábado'), 7 => __('Domingo'),
        ];
    }

    private function profissional(Request $request): ?Profissional
    {
        return $request->user()->profissional;
    }

    private function semFicha(): RedirectResponse
    {
        return redirect()->route('home')
            ->with('error', __('A sua conta não tem ficha de profissional associada. Contacte a receção.'));
    }
}
