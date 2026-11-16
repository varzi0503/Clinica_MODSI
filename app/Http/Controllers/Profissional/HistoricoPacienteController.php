<?php

namespace App\Http\Controllers\Profissional;

use App\Http\Controllers\Controller;
use App\Models\Paciente;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * UC15 — o profissional consulta o histórico de um dos seus pacientes (issue #57).
 * Vê apenas as consultas que esse paciente teve consigo: as de outros profissionais ficam de
 * fora (RNF03). Que o paciente é seu é verificado na rota (PacientePolicy::verHistorico).
 */
class HistoricoPacienteController extends Controller
{
    public function __invoke(Request $request, Paciente $paciente): View
    {
        $consultas = $paciente->consultas()
            ->with(['especialidade', 'historico'])
            ->where('profissional_id', $request->user()->profissional->id)
            ->orderByDesc('inicio')
            ->paginate(20);

        return view('profissional.historico', ['paciente' => $paciente, 'consultas' => $consultas]);
    }
}
