<?php

namespace Tests\Feature;

use App\Models\Consulta;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaGeralTest extends TestCase
{
    use RefreshDatabase;

    private User $rececionista;

    private Profissional $ana;

    private Profissional $bruno;

    protected function setUp(): void
    {
        parent::setUp();

        // Quarta-feira, 18 nov. 2026: a semana vai de 16 (seg.) a 22 (dom.).
        $this->travelTo(CarbonImmutable::parse('2026-11-18 08:00:00'));

        $this->rececionista = User::factory()->rececionista()->create();
        $this->ana = Profissional::factory()->for(User::factory()->profissional()->state(['name' => 'Ana Ferreira']))->comEspecialidade()->create();
        $this->bruno = Profissional::factory()->for(User::factory()->profissional()->state(['name' => 'Bruno Carvalho']))->comEspecialidade()->create();
    }

    private function consulta(Profissional $de, string $inicio, string $estado = 'confirmada', string $paciente = 'Paciente'): Consulta
    {
        return Consulta::factory()->for($de)->for(Paciente::factory()->semConta()->state(['nome' => $paciente]))
            ->{$estado}()->create(['inicio' => $inicio]);
    }

    public function test_dia_mostra_as_consultas_de_todos_os_profissionais_por_nome_e_por_hora(): void
    {
        $this->consulta($this->bruno, '2026-11-18 09:00:00', paciente: 'Paciente do Bruno');
        $this->consulta($this->ana, '2026-11-18 11:00:00', paciente: 'Segunda da Ana');
        $this->consulta($this->ana, '2026-11-18 09:30:00', 'pedida', 'Primeira da Ana');
        $this->consulta($this->ana, '2026-11-19 09:30:00', paciente: 'De amanhã');

        $this->actingAs($this->rececionista)->get(route('rececao.agenda.index'))
            ->assertOk()
            ->assertSeeInOrder(['Ana Ferreira', '09:30', 'Primeira da Ana', 'Pedida', '11:00', 'Segunda da Ana', 'Bruno Carvalho', '09:00', 'Paciente do Bruno'])
            ->assertDontSee('De amanhã');
    }

    public function test_so_aparecem_as_consultas_que_ocupam_vaga(): void
    {
        $this->consulta($this->ana, '2026-11-18 09:00:00', 'realizada', 'Já atendida');
        $this->consulta($this->ana, '2026-11-18 10:00:00', 'cancelada', 'Cancelou');
        $this->consulta($this->ana, '2026-11-18 10:30:00', 'emEspera', 'Em lista de espera');
        $this->consulta($this->ana, '2026-11-18 11:00:00', 'rejeitada', 'Rejeitado');

        $this->actingAs($this->rececionista)->get(route('rececao.agenda.index'))
            ->assertSee('Já atendida')
            ->assertDontSee('Cancelou')->assertDontSee('Em lista de espera')->assertDontSee('Rejeitado');
    }

    public function test_semana_filtro_por_profissional_e_navegacao(): void
    {
        $this->consulta($this->ana, '2026-11-16 09:00:00', 'realizada', 'Segunda-feira');
        $this->consulta($this->bruno, '2026-11-20 09:00:00', paciente: 'Sexta-feira');
        $this->consulta($this->ana, '2026-11-23 09:00:00', paciente: 'Semana seguinte');

        $this->actingAs($this->rececionista)->get(route('rececao.agenda.index', ['vista' => 'semana']))
            ->assertOk()->assertSee('16/11')->assertSee('22/11/2026')
            ->assertSee('Segunda-feira')->assertSee('Sexta-feira')->assertDontSee('Semana seguinte');

        $this->actingAs($this->rececionista)->get(route('rececao.agenda.index', ['vista' => 'semana', 'profissional_id' => $this->bruno->id]))
            ->assertSee('Sexta-feira')->assertDontSee('Segunda-feira');

        $this->actingAs($this->rececionista)->get(route('rececao.agenda.index', ['vista' => 'semana', 'data' => '2026-11-25']))
            ->assertSee('Semana seguinte')->assertDontSee('Sexta-feira');
    }

    public function test_dia_sem_consultas_e_parametros_invalidos(): void
    {
        $this->actingAs($this->rececionista)->get(route('rececao.agenda.index'))->assertOk()->assertSee('Sem consultas.');

        $this->actingAs($this->rececionista)->get(route('rececao.agenda.index', ['data' => 'amanhã']))->assertSessionHasErrors('data');
        $this->actingAs($this->rececionista)->get(route('rececao.agenda.index', ['vista' => 'mes']))->assertSessionHasErrors('vista');
        $this->actingAs($this->rececionista)->get(route('rececao.agenda.index', ['profissional_id' => 999999]))->assertSessionHasErrors('profissional_id');
    }

    public function test_so_a_rececao_ve_a_agenda_geral(): void
    {
        $this->get(route('rececao.agenda.index'))->assertRedirect(route('login'));

        foreach ([User::factory()->administrador()->create(), User::factory()->paciente()->create(), $this->ana->user] as $user) {
            $this->actingAs($user)->get(route('rececao.agenda.index'))->assertForbidden();
        }
    }

    public function test_pagina_em_ingles(): void
    {
        $this->actingAs($this->rececionista)->withSession(['locale' => 'en'])
            ->get(route('rececao.agenda.index'))->assertSee('General agenda')->assertSee('No appointments.');
    }
}
