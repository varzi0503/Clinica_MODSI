<?php

namespace Tests\Feature;

use App\Enums\EstadoConsulta;
use App\Models\Consulta;
use App\Models\Especialidade;
use App\Models\Horario;
use App\Models\Paciente;
use App\Models\Perfil;
use App\Models\Profissional;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PedidoPacienteTest extends TestCase
{
    use RefreshDatabase;

    private User $userPaciente;

    private Paciente $paciente;

    private Profissional $medica;

    private Especialidade $cardiologia;

    private Perfil $perfilPaciente;

    protected function setUp(): void
    {
        parent::setUp();

        // Segunda-feira, 16 nov. 2026, 08:00.
        $this->travelTo(CarbonImmutable::parse('2026-11-16 08:00:00'));

        $this->perfilPaciente = Perfil::create(['nome' => 'paciente']);
        $perfilProfissional = Perfil::create(['nome' => 'profissional']);
        Perfil::create(['nome' => 'rececionista']);

        $this->userPaciente = User::factory()->create(['perfil_id' => $this->perfilPaciente->id]);
        $this->paciente = Paciente::create(['user_id' => $this->userPaciente->id, 'nome' => 'Maria', 'num_utente' => '123456789']);

        $this->cardiologia = Especialidade::create(['nome' => 'Cardiologia']);
        $this->medica = Profissional::create([
            'user_id' => User::factory()->create(['perfil_id' => $perfilProfissional->id, 'name' => 'Dra. Ana'])->id,
            'tipo' => 'medico', 'num_cedula' => '1',
        ]);
        $this->medica->especialidades()->attach($this->cardiologia->id);
        Horario::create(['profissional_id' => $this->medica->id, 'dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fim' => '11:00']);
    }

    private function pedir(array $alteracoes = [], ?User $como = null)
    {
        return $this->actingAs($como ?? $this->userPaciente)->post(route('paciente.pedidos.store'), $alteracoes + [
            'especialidade_id' => $this->cardiologia->id,
            'profissional_id' => $this->medica->id,
            'inicio' => '2026-11-16T09:30',
            'motivo_consulta' => 'Rotina',
        ]);
    }

    public function test_pedido_fica_pendente_e_aparece_na_lista_da_rececao(): void
    {
        $this->pedir()->assertRedirect(route('paciente.pedidos.create'))->assertSessionHas('success');

        $consulta = Consulta::sole();
        $this->assertSame(EstadoConsulta::Pedida, $consulta->estado);
        $this->assertSame($this->paciente->id, $consulta->paciente_id);
        $this->assertSame($this->userPaciente->id, $consulta->historico()->sole()->user_id);

        $rececionista = User::factory()->create(['perfil_id' => Perfil::where('nome', 'rececionista')->value('id')]);
        $this->actingAs($rececionista)->get(route('rececao.pedidos.index'))->assertSee('Maria')->assertSee('16/11/2026 09:30');
    }

    public function test_paciente_id_vindo_do_formulario_e_ignorado(): void
    {
        $outro = Paciente::create(['nome' => 'Outra pessoa', 'num_utente' => '987654321']);

        $this->pedir(['paciente_id' => $outro->id])->assertSessionHasNoErrors();

        $this->assertSame($this->paciente->id, Consulta::sole()->paciente_id);
    }

    public function test_recusa_vaga_ocupada_ou_fora_do_horario(): void
    {
        $this->pedir()->assertSessionHasNoErrors();

        $outroUser = User::factory()->create(['perfil_id' => $this->perfilPaciente->id]);
        Paciente::create(['user_id' => $outroUser->id, 'nome' => 'Rui', 'num_utente' => '222222222']);

        $this->pedir([], $outroUser)->assertSessionHasErrors('inicio');                          // já ocupada
        $this->pedir(['inicio' => '2026-11-16T12:00'], $outroUser)->assertSessionHasErrors('inicio'); // fora do horário

        $this->assertSame(1, Consulta::count());
    }

    public function test_conta_de_paciente_sem_ficha_e_reencaminhada(): void
    {
        $semFicha = User::factory()->create(['perfil_id' => $this->perfilPaciente->id]);

        $this->actingAs($semFicha)->get(route('paciente.pedidos.create'))
            ->assertRedirect(route('home'))->assertSessionHas('error');
        $this->pedir([], $semFicha)->assertRedirect(route('home'));

        $this->assertSame(0, Consulta::count());
    }

    public function test_so_pacientes_pedem(): void
    {
        $this->get(route('paciente.pedidos.create'))->assertRedirect(route('login'));

        $this->actingAs($this->medica->user)->get(route('paciente.pedidos.create'))->assertForbidden();
        $this->pedir([], $this->medica->user)->assertForbidden();
    }

    public function test_pagina_de_pedido_carrega_sem_seletor_de_paciente(): void
    {
        $this->actingAs($this->userPaciente)->get(route('paciente.pedidos.create'))
            ->assertOk()
            ->assertSee('Cardiologia')
            ->assertDontSee('name="paciente_id"', false);
    }
}
