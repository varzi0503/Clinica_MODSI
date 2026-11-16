<?php

use App\Http\Controllers\Admin\UtilizadorController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistoController;
use App\Http\Controllers\DadosPessoaisController;
use App\Http\Controllers\DisponibilidadeController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificacaoController;
use App\Http\Controllers\Paciente\ConsultaController as ConsultaDoPacienteController;
use App\Http\Controllers\Paciente\PedidoController;
use App\Http\Controllers\Profissional\AgendaController;
use App\Http\Controllers\Profissional\CancelarConsultaController;
use App\Http\Controllers\Profissional\ConsultaController as ConsultaDoProfissionalController;
use App\Http\Controllers\Profissional\HistoricoPacienteController;
use App\Http\Controllers\Profissional\HorarioController;
use App\Http\Controllers\Profissional\RegistoConsultaController;
use App\Http\Controllers\Rececao\AgendaGeralController;
use App\Http\Controllers\Rececao\ConsultaController as ConsultaDaRececaoController;
use App\Http\Controllers\Rececao\EspecialidadeController;
use App\Http\Controllers\Rececao\EstatisticasController;
use App\Http\Controllers\Rececao\MarcacaoController;
use App\Http\Controllers\Rececao\PacienteController;
use App\Http\Controllers\Rececao\PedidoConsultaController;
use App\Http\Controllers\Rececao\ProfissionalController;
use App\Models\Perfil;
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

// Documentos de uma consulta (issue #76): só o paciente e o profissional dessa consulta (D37).
Route::middleware(['auth', 'perfil:'.Perfil::PACIENTE.','.Perfil::PROFISSIONAL])->name('documentos.')->group(function () {
    Route::post('/consultas/{consulta}/documentos', [DocumentoController::class, 'store'])->can('verDocumentos', 'consulta')->name('store');
    Route::get('/documentos/{documento}', [DocumentoController::class, 'show'])->can('descarregar', 'documento')->name('show');
    Route::delete('/documentos/{documento}', [DocumentoController::class, 'destroy'])->can('apagar', 'documento')->name('destroy');
});

// Notificações da própria conta (issue #74).
Route::get('/notificacoes', [NotificacaoController::class, 'index'])
    ->middleware(['auth', 'perfil:'.implode(',', Perfil::NOMES)])
    ->name('notificacoes.index');

// UC10 — qualquer conta autenticada; cada uma só vê e altera os seus próprios dados.
// O middleware perfil: com todos os perfis serve para expulsar contas desativadas a meio da sessão.
Route::middleware(['auth', 'perfil:'.implode(',', Perfil::NOMES)])->prefix('dados-pessoais')->name('dados-pessoais.')->group(function () {
    Route::get('/', [DadosPessoaisController::class, 'edit'])->name('edit');
    Route::put('/', [DadosPessoaisController::class, 'update'])->name('update');
    Route::put('/palavra-passe', [DadosPessoaisController::class, 'atualizarPalavraPasse'])->name('palavra-passe');
});

Route::middleware(['auth', 'perfil:administrador'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/utilizadores', [UtilizadorController::class, 'index'])->name('utilizadores.index');
    Route::get('/utilizadores/novo', [UtilizadorController::class, 'create'])->name('utilizadores.create');
    Route::post('/utilizadores', [UtilizadorController::class, 'store'])->name('utilizadores.store');
    Route::patch('/utilizadores/{user}/perfil', [UtilizadorController::class, 'atualizarPerfil'])->name('utilizadores.perfil');
    Route::patch('/utilizadores/{user}/ativar', [UtilizadorController::class, 'ativar'])->name('utilizadores.ativar');
    Route::patch('/utilizadores/{user}/desativar', [UtilizadorController::class, 'desativar'])->name('utilizadores.desativar');
});

Route::middleware(['auth', 'perfil:paciente'])->prefix('paciente')->name('paciente.')->group(function () {
    Route::get('/consultas/pedir', [PedidoController::class, 'create'])->name('pedidos.create');
    Route::post('/consultas/pedidos', [PedidoController::class, 'store'])->name('pedidos.store');
    Route::get('/consultas', [ConsultaDoPacienteController::class, 'index'])->name('consultas.index');
    Route::post('/consultas/{consulta}/cancelar', [ConsultaDoPacienteController::class, 'cancelar'])->can('cancelar', 'consulta')->name('consultas.cancelar');
    Route::get('/consultas/{consulta}', [ConsultaDoPacienteController::class, 'show'])->whereNumber('consulta')->can('verDocumentos', 'consulta')->name('consultas.show');
});

Route::middleware(['auth', 'perfil:profissional'])->prefix('profissional')->name('profissional.')->group(function () {
    Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda.index');
    Route::post('/consultas/{consulta}/realizada', [RegistoConsultaController::class, 'realizada'])->can('registar', 'consulta')->name('consultas.realizada');
    Route::post('/consultas/{consulta}/faltou', [RegistoConsultaController::class, 'faltou'])->can('registar', 'consulta')->name('consultas.faltou');
    Route::post('/consultas/{consulta}/cancelar', CancelarConsultaController::class)->can('cancelar', 'consulta')->name('consultas.cancelar');
    Route::get('/consultas/{consulta}', [ConsultaDoProfissionalController::class, 'show'])->can('verObservacoes', 'consulta')->name('consultas.show');
    Route::post('/consultas/{consulta}/observacoes', [ConsultaDoProfissionalController::class, 'observar'])->can('verObservacoes', 'consulta')->name('consultas.observar');
    Route::get('/pacientes/{paciente}/historico', HistoricoPacienteController::class)->can('verHistorico', 'paciente')->name('pacientes.historico');
    Route::get('/horario', [HorarioController::class, 'index'])->name('horario.index');
    Route::post('/horario', [HorarioController::class, 'store'])->name('horario.store');
    Route::delete('/horario/{horario}', [HorarioController::class, 'destroy'])->can('apagar', 'horario')->name('horario.destroy');
});

Route::middleware(['auth', 'perfil:rececionista'])->prefix('rececao')->name('rececao.')->group(function () {
    Route::get('/pedidos', [PedidoConsultaController::class, 'index'])->name('pedidos.index');
    Route::post('/pedidos/{consulta}/confirmar', [PedidoConsultaController::class, 'confirmar'])->can('confirmar', 'consulta')->name('pedidos.confirmar');
    Route::post('/pedidos/{consulta}/rejeitar', [PedidoConsultaController::class, 'rejeitar'])->can('rejeitar', 'consulta')->name('pedidos.rejeitar');
    Route::get('/consultas/marcar', [MarcacaoController::class, 'create'])->name('consultas.create');
    Route::post('/consultas', [MarcacaoController::class, 'store'])->name('consultas.store');
    Route::get('/consultas', [ConsultaDaRececaoController::class, 'index'])->name('consultas.index');
    Route::post('/consultas/{consulta}/cancelar', [ConsultaDaRececaoController::class, 'cancelar'])->can('cancelar', 'consulta')->name('consultas.cancelar');
    Route::get('/consultas/{consulta}/reagendar', [ConsultaDaRececaoController::class, 'reagendamento'])->can('reagendar', 'consulta')->name('consultas.reagendamento');
    Route::post('/consultas/{consulta}/reagendar', [ConsultaDaRececaoController::class, 'reagendar'])->can('reagendar', 'consulta')->name('consultas.reagendar');
    Route::get('/pacientes', [PacienteController::class, 'index'])->name('pacientes.index');
    Route::get('/pacientes/pesquisa', [PacienteController::class, 'pesquisa'])->name('pacientes.pesquisa');
    Route::get('/pacientes/novo', [PacienteController::class, 'create'])->name('pacientes.create');
    Route::post('/pacientes', [PacienteController::class, 'store'])->name('pacientes.store');
    Route::get('/pacientes/{paciente}/editar', [PacienteController::class, 'edit'])->name('pacientes.edit');
    Route::put('/pacientes/{paciente}', [PacienteController::class, 'update'])->name('pacientes.update');
    Route::post('/pacientes/{paciente}/conta', [PacienteController::class, 'associarConta'])->name('pacientes.conta');
    Route::get('/profissionais', [ProfissionalController::class, 'index'])->name('profissionais.index');
    Route::get('/profissionais/novo', [ProfissionalController::class, 'create'])->name('profissionais.create');
    Route::post('/profissionais', [ProfissionalController::class, 'store'])->name('profissionais.store');
    Route::get('/profissionais/{profissional}/editar', [ProfissionalController::class, 'edit'])->name('profissionais.edit');
    Route::put('/profissionais/{profissional}', [ProfissionalController::class, 'update'])->name('profissionais.update');
    Route::get('/especialidades', [EspecialidadeController::class, 'index'])->name('especialidades.index');
    Route::get('/especialidades/nova', [EspecialidadeController::class, 'create'])->name('especialidades.create');
    Route::post('/especialidades', [EspecialidadeController::class, 'store'])->name('especialidades.store');
    Route::get('/especialidades/{especialidade}/editar', [EspecialidadeController::class, 'edit'])->name('especialidades.edit');
    Route::put('/especialidades/{especialidade}', [EspecialidadeController::class, 'update'])->name('especialidades.update');
    Route::delete('/especialidades/{especialidade}', [EspecialidadeController::class, 'destroy'])->name('especialidades.destroy');
    Route::get('/estatisticas', [EstatisticasController::class, 'index'])->name('estatisticas.index');
    Route::get('/agenda', [AgendaGeralController::class, 'index'])->name('agenda.index');
});

// POST (e não GET) porque altera o estado da sessão; o formulário leva token CSRF.
Route::post('/idioma/{locale}', LocaleController::class)
    ->whereIn('locale', array_keys(config('app.available_locales')))
    ->name('locale.update');
