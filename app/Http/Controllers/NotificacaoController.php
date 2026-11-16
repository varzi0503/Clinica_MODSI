<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/** As notificações da conta autenticada (issue #74). Abrir a página marca-as como lidas. */
class NotificacaoController extends Controller
{
    public function index(Request $request): View
    {
        $notificacoes = $request->user()->notifications()->paginate(20);

        // Guarda-se quais estavam por ler antes de as marcar, para a página as destacar desta vez.
        $porLer = $notificacoes->getCollection()->whereNull('read_at')->modelKeys();
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return view('notificacoes', ['notificacoes' => $notificacoes, 'porLer' => $porLer]);
    }
}
