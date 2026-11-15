<?php

namespace App\Http\Controllers\Rececao;

use App\Enums\EstadoConsulta;
use App\Http\Controllers\Controller;
use App\Services\Estatisticas;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * UC25 — a receção consulta as estatísticas (issue #73): totais de consultas por estado,
 * profissional e especialidade, com um período opcional sobre a data da consulta.
 */
class EstatisticasController extends Controller
{
    /**
     * Uma cor fixa por estado, igual em todos os gráficos. A paleta foi verificada para
     * daltonismo; três das cores têm pouco contraste com o fundo, por isso cada gráfico
     * tem sempre a tabela com os números ao lado.
     */
    private const CORES = [
        'em_espera' => '#2a78d6',
        'pedida' => '#eb6834',
        'confirmada' => '#1baf7a',
        'rejeitada' => '#eda100',
        'cancelada' => '#e87ba4',
        'realizada' => '#008300',
        'faltou' => '#6250d6',
    ];

    public function index(Request $request, Estatisticas $estatisticas): View
    {
        $filtros = $request->validate([
            'de' => ['nullable', 'date_format:Y-m-d'],
            'ate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:de'],
        ]);

        $de = isset($filtros['de']) ? CarbonImmutable::createFromFormat('Y-m-d|', $filtros['de']) : null;
        $ate = isset($filtros['ate']) ? CarbonImmutable::createFromFormat('Y-m-d|', $filtros['ate']) : null;

        $porEstado = $estatisticas->porEstado($de, $ate);
        $profissionais = $estatisticas->porProfissional($de, $ate);
        $especialidades = $estatisticas->porEspecialidade($de, $ate);

        $estados = collect(EstadoConsulta::cases())->map(fn (EstadoConsulta $estado) => [
            'valor' => $estado->value,
            'nome' => $estado->label(),
            'cor' => self::CORES[$estado->value],
            'total' => $porEstado[$estado->value],
        ]);

        return view('rececao.estatisticas', [
            'filtros' => $filtros,
            'total' => array_sum($porEstado),
            'estados' => $estados,
            'profissionais' => $profissionais,
            'especialidades' => $especialidades,
            // O que o resources/js/estatisticas.js precisa para desenhar os três gráficos.
            'graficos' => [
                'estados' => $estados->values(),
                'profissionais' => $profissionais->where('total', '>', 0)->values(),
                'especialidades' => $especialidades->where('total', '>', 0)->values(),
            ],
        ]);
    }
}
