<?php

namespace App\Http\Controllers\Profissional;

use App\Enums\EstadoConsulta;
use App\Exceptions\TransicaoEstadoInvalida;
use App\Http\Controllers\Controller;
use App\Models\Consulta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * UC13 — o profissional regista a consulta como realizada ou falta (issue #63).
 * Só consultas confirmadas, só depois da hora de início, só as suas.
 */
class RegistoConsultaController extends Controller
{
    public function realizada(Request $request, Consulta $consulta): RedirectResponse
    {
        return $this->registar($request, $consulta, EstadoConsulta::Realizada, __('Consulta registada como realizada.'));
    }

    public function faltou(Request $request, Consulta $consulta): RedirectResponse
    {
        return $this->registar($request, $consulta, EstadoConsulta::Faltou, __('Falta registada.'));
    }

    private function registar(Request $request, Consulta $consulta, EstadoConsulta $novo, string $mensagem): RedirectResponse
    {
        $profissional = $request->user()->profissional;
        abort_unless($profissional && $consulta->profissional_id === $profissional->id, 403);

        if ($consulta->inicio->isFuture()) {
            return back()->with('error', __('Só pode registar a consulta depois da hora de início.'));
        }

        try {
            $consulta->mudarEstado($novo, $request->user());
        } catch (TransicaoEstadoInvalida) {
            return back()->with('error', __('Só é possível registar consultas confirmadas.'));
        }

        return back()->with('success', $mensagem);
    }
}
