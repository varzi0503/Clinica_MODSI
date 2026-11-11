<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistoController;
use App\Http\Controllers\DisponibilidadeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Paciente\PedidoController;
use App\Http\Controllers\Profissional\AgendaController;
use App\Http\Controllers\Profissional\HorarioController;
use App\Http\Controllers\Rececao\MarcacaoController;
use App\Http\Controllers\Rececao\PedidoConsultaController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

// UC06 — público: só devolve inícios de vagas livres, sem dados de pacientes.
Route::get('/disponibilidade', [DisponibilidadeController::class, 'index'])->name('disponibilidade.index');
Route::get('/disponibilidade/vagas', [DisponibilidadeController::class, 'vagas'])
    ->middleware('throttle:60,1')
    ->name('disponibilidade.vagas');

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [LoginController::class, 'create'])->name('login');
    // throttle por IP; o bloqueio por conta é feito no controlador.
    Route::post('/entrar', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
    Route::get('/registar', [RegistoController::class, 'create'])->name('registo');
    Route::post('/registar', [RegistoController::class, 'store'])->middleware('throttle:5,1')->name('registo.store');
});

// POST (e não GET) porque termina a sessão; o formulário leva token CSRF.
Route::post('/sair', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'perfil:paciente'])->prefix('paciente')->name('paciente.')->group(function () {
    Route::get('/consultas/pedir', [PedidoController::class, 'create'])->name('pedidos.create');
    Route::post('/consultas/pedidos', [PedidoController::class, 'store'])->name('pedidos.store');
});

Route::middleware(['auth', 'perfil:profissional'])->prefix('profissional')->name('profissional.')->group(function () {
    Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda.index');
    Route::get('/horario', [HorarioController::class, 'index'])->name('horario.index');
    Route::post('/horario', [HorarioController::class, 'store'])->name('horario.store');
    Route::delete('/horario/{horario}', [HorarioController::class, 'destroy'])->name('horario.destroy');
});

Route::middleware(['auth', 'perfil:rececionista'])->prefix('rececao')->name('rececao.')->group(function () {
    Route::get('/pedidos', [PedidoConsultaController::class, 'index'])->name('pedidos.index');
    Route::post('/pedidos/{consulta}/confirmar', [PedidoConsultaController::class, 'confirmar'])->name('pedidos.confirmar');
    Route::post('/pedidos/{consulta}/rejeitar', [PedidoConsultaController::class, 'rejeitar'])->name('pedidos.rejeitar');
    Route::get('/consultas/marcar', [MarcacaoController::class, 'create'])->name('consultas.create');
    Route::post('/consultas', [MarcacaoController::class, 'store'])->name('consultas.store');
});

// POST (e não GET) porque altera o estado da sessão; o formulário leva token CSRF.
Route::post('/idioma/{locale}', LocaleController::class)
    ->whereIn('locale', array_keys(config('app.available_locales')))
    ->name('locale.update');
