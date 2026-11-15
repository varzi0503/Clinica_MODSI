<?php

namespace App\Services;

use App\Enums\EstadoConsulta;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Totais de consultas por estado, profissional e especialidade (issue #73), lidos das vistas SQL
 * da issue #36.
 *
 * Sem período, os totais vêm diretamente das vistas v_estatisticas_*. Com período, essas vistas
 * não servem (já vêm agregadas), por isso agrupa-se a v_consultas_detalhe pela data da consulta;
 * a lista de profissionais e de especialidades continua a vir das vistas de totais, para quem
 * não teve consultas no período aparecer com zeros em vez de desaparecer.
 */
class Estatisticas
{
    /** Coluna das vistas de totais que conta cada estado. */
    public const COLUNAS = [
        'em_espera' => 'em_espera',
        'pedida' => 'pedidas',
        'confirmada' => 'confirmadas',
        'rejeitada' => 'rejeitadas',
        'cancelada' => 'canceladas',
        'realizada' => 'realizadas',
        'faltou' => 'faltas',
    ];

    /**
     * Total de consultas em cada estado, incluindo os estados sem consultas.
     *
     * @return array<string, int> valor do estado => total
     */
    public function porEstado(?CarbonImmutable $de = null, ?CarbonImmutable $ate = null): array
    {
        $totais = $this->temPeriodo($de, $ate)
            ? $this->noPeriodo($de, $ate)->groupBy('estado')->selectRaw('estado, count(*) as total')->pluck('total', 'estado')
            : DB::table('v_estatisticas_estado')->pluck('total', 'estado');

        return collect(EstadoConsulta::cases())
            ->mapWithKeys(fn (EstadoConsulta $estado) => [$estado->value => (int) ($totais[$estado->value] ?? 0)])
            ->all();
    }

    /**
     * Uma linha por profissional, por ordem de nome.
     *
     * @return Collection<int, array{id: int, nome: string, tipo: string, total: int, estados: array<string, int>}>
     */
    public function porProfissional(?CarbonImmutable $de = null, ?CarbonImmutable $ate = null): Collection
    {
        return $this->linhas('v_estatisticas_profissional', 'profissional_id', 'profissional_nome', $de, $ate)
            ->map(fn (array $linha) => ['tipo' => $linha['origem']->profissional_tipo] + collect($linha)->except('origem')->all());
    }

    /**
     * Uma linha por especialidade, por ordem de nome.
     *
     * @return Collection<int, array{id: int, nome: string, total: int, estados: array<string, int>}>
     */
    public function porEspecialidade(?CarbonImmutable $de = null, ?CarbonImmutable $ate = null): Collection
    {
        return $this->linhas('v_estatisticas_especialidade', 'especialidade_id', 'especialidade_nome', $de, $ate)
            ->map(fn (array $linha) => collect($linha)->except('origem')->all());
    }

    /**
     * @return Collection<int, array{id: int, nome: string, total: int, estados: array<string, int>, origem: object}>
     */
    private function linhas(string $vista, string $colunaId, string $colunaNome, ?CarbonImmutable $de, ?CarbonImmutable $ate): Collection
    {
        $totais = DB::table($vista)->orderBy($colunaNome)->get();

        // Com período, as contagens da vista de totais são substituídas pelas do período.
        $doPeriodo = $this->temPeriodo($de, $ate)
            ? $this->noPeriodo($de, $ate)->groupBy($colunaId)->selectRaw("{$colunaId}, {$this->contagens()}")->get()->keyBy($colunaId)
            : null;

        return $totais->map(function (object $linha) use ($colunaId, $colunaNome, $doPeriodo) {
            $contagens = $doPeriodo === null ? $linha : $doPeriodo->get($linha->{$colunaId});

            return [
                'id' => (int) $linha->{$colunaId},
                'nome' => $linha->{$colunaNome},
                'total' => (int) ($contagens->total ?? 0),
                'estados' => collect(self::COLUNAS)->map(fn (string $coluna) => (int) ($contagens->{$coluna} ?? 0))->all(),
                'origem' => $linha,
            ];
        })->values();
    }

    private function temPeriodo(?CarbonImmutable $de, ?CarbonImmutable $ate): bool
    {
        return $de !== null || $ate !== null;
    }

    /** Consultas marcadas para um dia dentro do período (os dois dias incluídos). */
    private function noPeriodo(?CarbonImmutable $de, ?CarbonImmutable $ate): Builder
    {
        return DB::table('v_consultas_detalhe')
            ->when($de, fn (Builder $q) => $q->where('inicio', '>=', $de->startOfDay()))
            ->when($ate, fn (Builder $q) => $q->where('inicio', '<', $ate->addDay()->startOfDay()));
    }

    /** As mesmas contagens por estado das vistas de totais, em SQL portátil (CASE). */
    private function contagens(): string
    {
        return collect(self::COLUNAS)
            ->map(fn (string $coluna, string $estado) => "sum(case when estado = '{$estado}' then 1 else 0 end) as {$coluna}")
            ->prepend('count(*) as total')
            ->implode(', ');
    }
}
