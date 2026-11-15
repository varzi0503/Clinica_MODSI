<?php

use App\Exceptions\TransicaoEstadoInvalida;
use App\Http\Middleware\EnsurePerfil;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
        ]);

        $middleware->alias([
            'perfil' => EnsurePerfil::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Conflito de estado (D28): a consulta já não está no estado de que a ação precisava,
        // em regra porque outra pessoa a tratou entretanto. Fica aqui para os controladores
        // não repetirem o mesmo try/catch em cada caso de uso.
        $exceptions->render(function (TransicaoEstadoInvalida $e, Request $request) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->mensagemParaUtilizador()], 409)
                : back()->with('error', $e->mensagemParaUtilizador());
        });
    })->create();
