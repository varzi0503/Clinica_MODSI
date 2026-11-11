<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso: ->middleware('perfil:rececionista') ou ->middleware('perfil:rececionista,profissional').
 * Pressupõe 'auth' antes na pilha. A verificação é feita no servidor,
 * nunca apenas escondendo botões nas vistas.
 */
class EnsurePerfil
{
    public function handle(Request $request, Closure $next, string ...$perfis): Response
    {
        $user = $request->user();

        // Conta desativada a meio da sessão: termina-a já, em vez de esperar pelo próximo login.
        if (! $user->ativo) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => __('A conta está desativada. Contacte a receção.')]);
        }

        abort_unless($user->temPerfil(...$perfis), 403);

        return $next($request);
    }
}
