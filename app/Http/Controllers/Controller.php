<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Resposta de uma ação, na convenção D28: {message} com o código dado quando o pedido
     * espera JSON; senão, volta à página anterior com a mensagem de sucesso ou de erro.
     */
    protected function responder(Request $request, string $mensagem, int $estado = 200): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $mensagem], $estado);
        }

        return back()->with($estado < 400 ? 'success' : 'error', $mensagem);
    }
}
