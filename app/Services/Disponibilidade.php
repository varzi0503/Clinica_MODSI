<?php

namespace App\Services;

use App\Models\Consulta;
use App\Models\Profissional;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Vagas livres de 30 min a partir dos horários semanais (issue #59, UC06).
 * Uma vaga está livre se cair num período de atendimento, for no futuro
 * e não tiver nenhuma consulta que a ocupe (vaga_ocupada = 1).
 * Pedidos em espera, rejeitados e cancelados não ocupam a vaga.
 */
class Disponibilidade
{
    public const DURACAO_MINUTOS = 30;

    /**
     * @return Collection<int, CarbonImmutable> inícios das vagas livres, por ordem
     */
    public function vagasLivres(Profissional $profissional, CarbonImmutable $de, CarbonImmutable $ate): Collection
    {
        $de = $de->startOfDay();
        $ate = $ate->endOfDay();
        $agora = CarbonImmutable::now();

        $horarios = $profissional->horarios()->get()->groupBy('dia_semana');

        $ocupadas = Consulta::query()
            ->where('profissional_id', $profissional->id)
            ->whereBetween('inicio', [$de, $ate])
            ->whereNotNull('vaga_ocupada')
            ->pluck('inicio')
            ->map(fn ($inicio) => CarbonImmutable::parse($inicio)->format('Y-m-d H:i'))
            ->flip();

        $vagas = collect();

        for ($dia = $de; $dia->lte($ate); $dia = $dia->addDay()) {
            foreach ($horarios->get($dia->isoWeekday(), []) as $horario) {
                $inicio = $dia->setTimeFromTimeString($horario->hora_inicio);
                $fim = $dia->setTimeFromTimeString($horario->hora_fim);

                for ($vaga = $inicio; $vaga->addMinutes(self::DURACAO_MINUTOS)->lte($fim); $vaga = $vaga->addMinutes(self::DURACAO_MINUTOS)) {
                    if ($vaga->gt($agora) && ! $ocupadas->has($vaga->format('Y-m-d H:i'))) {
                        $vagas->push($vaga);
                    }
                }
            }
        }

        return $vagas->sort()->values();
    }
}
