<?php

namespace Tests\Feature;

use App\Models\Consulta;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricosTest extends TestCase
{
    use RefreshDatabase;

    private Paciente $paciente;

    private Profissional $medica;

    private Profissional $colega;

    protected function setUp(): void
    {
        parent::setUp();

        // Quarta-feira, 18 nov. 2026, 10:00.
        $this->travelTo(CarbonImmutable::parse('2026-11-18 10:00:00'));

        $this->paciente = Paciente::factory()->create(['nome' => 'Paula Paciente']);
        $this->medica = Profissional::factory()->comEspecialidade()->create();
        $this->colega = Profissional::factory()->comEspecialidade()->create();
    }

    private function consulta(string $inicio, string $estado, ?Profissional $de = null, ?string $motivo = null): Consulta
    {
        return Consulta::factory()->for($this->paciente)->for($de ?? $this->medica)->{$estado}()
            ->create(['inicio' => $inicio, 'motivo_consulta' => $motivo]);
    }

    public function test_paciente_ve_as_passadas_da_mais_recente_para_a_mais_antiga_e_separadas_das_proximas(): void
    {
        $this->consulta('2026-11-10 10:00:00', 'realizada');
        $this->consulta('2026-10-05 09:00:00', 'faltou');
        $this->consulta('2026-11-25 10:00:00', 'confirmada');

        $this->actingAs($this->paciente->user)->get(route('paciente.consultas.index', ['vista' => 'passadas']))
            ->assertOk()
            ->assertSeeInOrder(['10/11/2026 10:00', 'Realizada', '05/10/2026 09:00', 'Faltou'])
            ->assertDontSee('25/11/2026 10:00')
            // O histórico é só de leitura.
            ->assertDontSee('Confirmar cancelamento');

        $this->actingAs($this->paciente->user)->get(route('paciente.consultas.index'))
            ->assertSee('25/11/2026 10:00')->assertDontSee('10/11/2026 10:00');
    }

    public function test_historico_mostra_o_motivo_das_canceladas_e_rejeitadas(): void
    {
        // A factory grava "Motivo de exemplo" no último registo do histórico destes dois estados.
        $cancelada = $this->consulta('2026-11-12 10:00:00', 'cancelada');
        $rejeitada = $this->consulta('2026-11-05 10:00:00', 'rejeitada');
        $realizada = $this->consulta('2026-11-03 10:00:00', 'realizada');

        $this->assertSame('Motivo de exemplo', $cancelada->motivoDoEstado());
        $this->assertSame('Motivo de exemplo', $rejeitada->motivoDoEstado());
        $this->assertNull($realizada->motivoDoEstado());

        $resposta = $this->actingAs($this->paciente->user)->get(route('paciente.consultas.index', ['vista' => 'passadas']));
        $resposta->assertSeeInOrder(['Cancelada', 'Motivo de exemplo', 'Rejeitada', 'Motivo de exemplo', 'Realizada']);
        $this->assertSame(2, substr_count($resposta->getContent(), 'Motivo de exemplo'));
    }

    public function test_paciente_so_ve_o_seu_proprio_historico(): void
    {
        Consulta::factory()->realizada()->for($this->medica)->create(['inicio' => '2026-11-09 09:30:00']);

        $this->actingAs($this->paciente->user)->get(route('paciente.consultas.index', ['vista' => 'passadas']))
            ->assertOk()->assertDontSee('09/11/2026 09:30')->assertSee('Ainda não tem consultas passadas.');

        $this->actingAs($this->paciente->user)->get(route('paciente.consultas.index', ['vista' => 'todas']))
            ->assertSessionHasErrors('vista');
    }

    public function test_profissional_ve_o_historico_do_paciente_so_com_as_consultas_consigo(): void
    {
        $this->consulta('2026-11-10 10:00:00', 'realizada', motivo: 'Dores no peito');
        $this->consulta('2026-11-25 10:00:00', 'confirmada');
        $this->consulta('2026-11-03 11:30:00', 'realizada', $this->colega, 'Consulta do colega');

        $this->actingAs($this->medica->user)->get(route('profissional.pacientes.historico', $this->paciente))
            ->assertOk()
            ->assertSee('Paula Paciente')
            ->assertSeeInOrder(['25/11/2026 10:00', '10/11/2026 10:00', 'Dores no peito'])
            ->assertDontSee('03/11/2026 11:30')
            ->assertDontSee('Consulta do colega');
    }

    public function test_profissional_nao_ve_o_historico_de_quem_nao_e_seu_paciente(): void
    {
        $this->consulta('2026-11-10 10:00:00', 'realizada', $this->colega);

        $this->actingAs($this->medica->user)->get(route('profissional.pacientes.historico', $this->paciente))->assertForbidden();
        $this->actingAs($this->colega->user)->get(route('profissional.pacientes.historico', $this->paciente))->assertOk();
    }

    public function test_so_profissionais_abrem_o_historico_de_um_paciente(): void
    {
        $this->consulta('2026-11-10 10:00:00', 'realizada');

        $this->get(route('profissional.pacientes.historico', $this->paciente))->assertRedirect(route('login'));

        foreach ([User::factory()->rececionista()->create(), User::factory()->administrador()->create(), $this->paciente->user] as $user) {
            $this->actingAs($user)->get(route('profissional.pacientes.historico', $this->paciente))->assertForbidden();
        }
    }

    public function test_agenda_liga_cada_paciente_ao_seu_historico(): void
    {
        $this->consulta('2026-11-19 10:00:00', 'confirmada');

        $this->actingAs($this->medica->user)->get(route('profissional.agenda.index'))
            ->assertSee(route('profissional.pacientes.historico', $this->paciente));
    }

    public function test_paginas_em_ingles(): void
    {
        $this->consulta('2026-11-10 10:00:00', 'realizada');

        $this->actingAs($this->paciente->user)->withSession(['locale' => 'en'])
            ->get(route('paciente.consultas.index', ['vista' => 'passadas']))->assertSee('Upcoming')->assertSee('Past');
        $this->actingAs($this->medica->user)->withSession(['locale' => 'en'])
            ->get(route('profissional.pacientes.historico', $this->paciente))->assertSee('Back to the agenda');
    }
}
