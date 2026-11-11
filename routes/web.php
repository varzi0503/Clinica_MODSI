<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Rececao\PedidoConsultaController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [LoginController::class, 'create'])->name('login');
    // throttle por IP; o bloqueio por conta é feito no controlador.
    Route::post('/entrar', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});

// POST (e não GET) porque termina a sessão; o formulário leva token CSRF.
Route::post('/sair', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'perfil:rececionista'])->prefix('rececao')->name('rececao.')->group(function () {
    Route::get('/pedidos', [PedidoConsultaController::class, 'index'])->name('pedidos.index');
    Route::post('/pedidos/{consulta}/confirmar', [PedidoConsultaController::class, 'confirmar'])->name('pedidos.confirmar');
    Route::post('/pedidos/{consulta}/rejeitar', [PedidoConsultaController::class, 'rejeitar'])->name('pedidos.rejeitar');
});

// POST (e não GET) porque altera o estado da sessão; o formulário leva token CSRF.
Route::post('/idioma/{locale}', LocaleController::class)
    ->whereIn('locale', array_keys(config('app.available_locales')))
    ->name('locale.update');
