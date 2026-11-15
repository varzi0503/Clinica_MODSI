<?php

namespace Tests\Feature;

use App\Enums\EstadoConsulta;
use App\Models\Consulta;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelamentosTest extends TestCase
{
    use RefreshDatabase;

    private Paciente $paciente;

    private Profissional $medica;

    protected function setUp(): void
    {
        parent::setUp();

        // Quarta-feira, 18 nov. 2026, 10:00.
        $this->travelTo(CarbonImmutable::parse('2026-11-18 10:00:00'));

        $this->paciente = Paciente::factory()->create();
        $this->medica = Profissional::factory()->comEspecialidade()->create();
    }

    private function consulta(string $inicio, string $estado = 'confirmada'): Consulta
    {
        return Consulta::factory()->for($this->paciente)->for($this->medica)->{$estado}()->create(['inicio' => $inicio]);
    }

    public function test_paciente_ve_as_suas_consultas_futuras_e_so_as_suas(): void
    {
        $this->consulta('2026-11-25 10:00:00');
        $this->consulta('2026-11-10 10:00:00', 'realizada');
        Consulta::factory()->confirmada()->for($this->medica)->create(['inicio' => '2026-11-26 11:30:00']);

        $this->actingAs($this->paciente->user)->get(route('paciente.consultas.index'))
            ->assertOk()
            ->assertSee('25/11/2026 10:00')
            ->assertSee($this->medica->user->name)
            ->assertDontSee('10/11/2026 10:00')
            ->assertDontSee('26/11/2026 11:30');
    }

    public function test_paciente_cancela_com_mais_de_24_horas_e_o_motivo_fica_no_historico(): void
    {
        $consulta = $this->consulta('2026-11-19 10:00:00');

        $this->actingAs($this->paciente->user)
            ->from(route('paciente.consultas.index'))
            ->post(route('paciente.consultas.cancelar', $consulta), ['motivo' => 'Viagem'])
            ->assertRedirect(route('paciente.consultas.index'))
            ->assertSessionHas('success');

        $consulta->refresh();
        $ultimo = $consulta->historico()->get()->last();
        $this->assertSame(EstadoConsulta::Cancelada, $consulta->estado);
        $this->assertSame(EstadoConsulta::Cancelada, $ultimo->estado);
        $this->assertSame('Viagem', $ultimo->motivo);
        $this->assertSame($this->paciente->user_id, $ultimo->user_id);
    }

    public function test_cancelar_liberta_a_vaga(): void
    {
        $consulta = $this->consulta('2026-11-25 10:00:00');

        $this->actingAs($this->paciente->user)->post(route('paciente.consultas.cancelar', $consulta));

        // Outro paciente pode agora ocupar a mesma vaga do mesmo profissional.
        $nova = Consulta::factory()->confirmada()->for($this->medica)->create(['inicio' => '2026-11-25 10:00:00']);
        $this->assertModelExists($nova);
    }

    public function test_paciente_nao_cancela_a_menos_de_24_horas(): void
    {
        $consulta = $this->consulta('2026-11-19 09:30:00');

        $this->actingAs($this->paciente->user)
            ->from(route('paciente.consultas.index'))
            ->post(route('paciente.consultas.cancelar', $consulta))
            ->assertRedirect(route('paciente.consultas.index'))
            ->assertSessionHas('error');

        $this->actingAs($this->paciente->user)
            ->postJson(route('paciente.consultas.cancelar', $consulta))
            ->assertStatus(422)
            ->assertJsonStructure(['message']);

        $this->assertSame(EstadoConsulta::Confirmada, $consulta->fresh()->estado);

        $this->actingAs($this->paciente->user)->get(route('paciente.consultas.index'))
            ->assertSee('Já não pode ser cancelada aqui.');
    }

    public function test_paciente_sai_da_lista_de_espera_mesmo_dentro_das_24_horas(): void
    {
        $emEspera = $this->consulta('2026-11-18 16:00:00', 'emEspera');

        $this->actingAs($this->paciente->user)
            ->post(route('paciente.consultas.cancelar', $emEspera))
            ->assertSessionHas('success');

        $this->assertSame(EstadoConsulta::Cancelada, $emEspera->fresh()->estado);
    }

    public function test_consulta_ja_cancelada_ou_realizada_devolve_409(): void
    {
        foreach ([$this->consulta('2026-11-25 10:00:00', 'cancelada'), $this->consulta('2026-11-10 10:00:00', 'realizada')] as $consulta) {
            $this->actingAs($this->paciente->user)
                ->postJson(route('paciente.consultas.cancelar', $consulta))
                ->assertStatus(409);
        }
    }

    public function test_paciente_nao_cancela_consultas_de_outro_paciente(): void
    {
        $alheia = Consulta::factory()->confirmada()->for($this->medica)->create(['inicio' => '2026-11-25 10:00:00']);

        $this->actingAs($this->paciente->user)->post(route('paciente.consultas.cancelar', $alheia))->assertForbidden();

        $this->assertSame(EstadoConsulta::Confirmada, $alheia->fresh()->estado);
    }

    public function test_profissional_cancela_consulta_confirmada_com_motivo_mesmo_em_cima_da_hora(): void
    {
        $consulta = $this->consulta('2026-11-18 11:00:00');

        $this->actingAs($this->medica->user)
            ->from(route('profissional.agenda.index'))
            ->post(route('profissional.consultas.cancelar', $consulta), ['motivo' => 'Doença'])
            ->assertRedirect(route('profissional.agenda.index'))
            ->assertSessionHas('success');

        $consulta->refresh();
        $this->assertSame(EstadoConsulta::Cancelada, $consulta->estado);
        $this->assertSame('Doença', $consulta->historico()->get()->last()->motivo);
        $this->assertSame($this->medica->user_id, $consulta->historico()->get()->last()->user_id);
    }

    public function test_profissional_tem_de_indicar_o_motivo(): void
    {
        $consulta = $this->consulta('2026-11-25 10:00:00');

        $this->actingAs($this->medica->user)
            ->post(route('profissional.consultas.cancelar', $consulta))
            ->assertSessionHasErrors('motivo');

        $this->actingAs($this->medica->user)
            ->postJson(route('profissional.consultas.cancelar', $consulta), ['motivo' => ''])
            ->assertStatus(422);

        $this->assertSame(EstadoConsulta::Confirmada, $consulta->fresh()->estado);
    }

    public function test_profissional_nao_cancela_pedidos_pendentes_nem_consultas_que_ja_comecaram(): void
    {
        $pedida = $this->consulta('2026-11-25 10:00:00', 'pedida');
        $jaComecou = $this->consulta('2026-11-18 09:30:00');

        foreach ([$pedida, $jaComecou] as $consulta) {
            $this->actingAs($this->medica->user)
                ->postJson(route('profissional.consultas.cancelar', $consulta), ['motivo' => 'Doença'])
                ->assertStatus(409);
        }

        $this->assertSame(EstadoConsulta::Pedida, $pedida->fresh()->estado);
        $this->assertSame(EstadoConsulta::Confirmada, $jaComecou->fresh()->estado);
    }

    public function test_profissional_nao_cancela_consultas_de_outro_profissional(): void
    {
        $consulta = $this->consulta('2026-11-25 10:00:00');
        $outro = Profissional::factory()->create();

        $this->actingAs($outro->user)
            ->post(route('profissional.consultas.cancelar', $consulta), ['motivo' => 'Doença'])
            ->assertForbidden();

        $this->assertSame(EstadoConsulta::Confirmada, $consulta->fresh()->estado);
    }

    public function test_agenda_mostra_cancelar_so_nas_confirmadas_que_ainda_nao_comecaram(): void
    {
        $futura = $this->consulta('2026-11-19 10:00:00');
        $passada = $this->consulta('2026-11-18 09:00:00');

        $this->actingAs($this->medica->user)->get(route('profissional.agenda.index'))
            ->assertOk()
            ->assertSee(route('profissional.consultas.cancelar', $futura))
            ->assertDontSee(route('profissional.consultas.cancelar', $passada));
    }

    public function test_cada_perfil_so_cancela_pela_sua_rota(): void
    {
        $consulta = $this->consulta('2026-11-25 10:00:00');
        $rececionista = User::factory()->rececionista()->create();

        $this->post(route('paciente.consultas.cancelar', $consulta))->assertRedirect(route('login'));
        $this->actingAs($rececionista)->post(route('paciente.consultas.cancelar', $consulta))->assertForbidden();
        $this->actingAs($this->medica->user)->post(route('paciente.consultas.cancelar', $consulta))->assertForbidden();
        $this->actingAs($this->paciente->user)->post(route('profissional.consultas.cancelar', $consulta), ['motivo' => 'x'])->assertForbidden();
        $this->actingAs($this->paciente->user)->get(route('profissional.agenda.index'))->assertForbidden();
        $this->actingAs($this->medica->user)->get(route('paciente.consultas.index'))->assertForbidden();

        $this->assertSame(EstadoConsulta::Confirmada, $consulta->fresh()->estado);
    }

    public function test_pagina_do_paciente_em_ingles(): void
    {
        $this->consulta('2026-11-25 10:00:00');

        $this->actingAs($this->paciente->user)->withSession(['locale' => 'en'])
            ->get(route('paciente.consultas.index'))
            ->assertSee('My appointments')->assertSee('Confirm cancellation')->assertSee('Confirmed');
    }
}
