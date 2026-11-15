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
        return $this->vagas($profissional, $de, $ate)['livres'];
    }

    /**
     * Vagas futuras do horário que já têm uma consulta a ocupá-las: é nestas que se pode
     * entrar em lista de espera (issue #75).
     *
     * @return Collection<int, CarbonImmutable> inícios das vagas ocupadas, por ordem
     */
    public function vagasOcupadas(Profissional $profissional, CarbonImmutable $de, CarbonImmutable $ate): Collection
    {
        return $this->vagas($profissional, $de, $ate)['ocupadas'];
    }

    /** A vaga que começa em $inicio está livre para este profissional? */
    public function estaLivre(Profissional $profissional, CarbonImmutable $inicio): bool
    {
        return $this->vagasLivres($profissional, $inicio, $inicio)
            ->contains(fn (CarbonImmutable $vaga) => $vaga->eq($inicio));
    }

    /** A vaga que começa em $inicio existe no horário, é futura e está ocupada? */
    public function estaOcupada(Profissional $profissional, CarbonImmutable $inicio): bool
    {
        return $this->vagasOcupadas($profissional, $inicio, $inicio)
            ->contains(fn (CarbonImmutable $vaga) => $vaga->eq($inicio));
    }

    /**
     * Todas as vagas futuras do horário semanal no intervalo, separadas em livres e ocupadas.
     *
     * @return array{livres: Collection<int, CarbonImmutable>, ocupadas: Collection<int, CarbonImmutable>}
     */
    private function vagas(Profissional $profissional, CarbonImmutable $de, CarbonImmutable $ate): array
    {
        $de = $de->startOfDay();
        $ate = $ate->endOfDay();
        $agora = CarbonImmutable::now();

        $horarios = $profissional->horarios()->get()->groupBy('dia_semana');

        $comConsulta = Consulta::query()
            ->where('profissional_id', $profissional->id)
            ->whereBetween('inicio', [$de, $ate])
            ->whereNotNull('vaga_ocupada')
            ->pluck('inicio')
            ->map(fn ($inicio) => CarbonImmutable::parse($inicio)->format('Y-m-d H:i'))
            ->flip();

        $livres = collect();
        $ocupadas = collect();

        for ($dia = $de; $dia->lte($ate); $dia = $dia->addDay()) {
            foreach ($horarios->get($dia->isoWeekday(), []) as $horario) {
                $inicio = $dia->setTimeFromTimeString($horario->hora_inicio);
                $fim = $dia->setTimeFromTimeString($horario->hora_fim);

                for ($vaga = $inicio; $vaga->addMinutes(self::DURACAO_MINUTOS)->lte($fim); $vaga = $vaga->addMinutes(self::DURACAO_MINUTOS)) {
                    if ($vaga->lte($agora)) {
                        continue;
                    }

                    ($comConsulta->has($vaga->format('Y-m-d H:i')) ? $ocupadas : $livres)->push($vaga);
                }
            }
        }

        return ['livres' => $livres->sort()->values(), 'ocupadas' => $ocupadas->sort()->values()];
    }
}
