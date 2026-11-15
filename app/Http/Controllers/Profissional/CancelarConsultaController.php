<?php

namespace App\Http\Controllers\Profissional;

use App\Http\Controllers\Controller;
use App\Models\Consulta;
use App\Services\Marcacoes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * UC14 — o profissional cancela uma consulta sua por indisponibilidade, com motivo (issue #64).
 * A posse é verificada na rota (ConsultaPolicy::cancelar).
 */
class CancelarConsultaController extends Controller
{
    public function __invoke(Request $request, Consulta $consulta, Marcacoes $marcacoes): RedirectResponse|JsonResponse
    {
        $dados = $request->validate(['motivo' => ['required', 'string', 'max:255']]);

        if (! $marcacoes->profissionalPodeCancelar($consulta)) {
            // Não está confirmada (ou já começou): é um conflito de estado, como na D28.
            return $this->responder($request, __('Só pode cancelar consultas confirmadas que ainda não começaram.'), 409);
        }

        $marcacoes->cancelar($consulta, $request->user(), $dados['motivo']);

        return $this->responder($request, __('Consulta cancelada.'));
    }
}
