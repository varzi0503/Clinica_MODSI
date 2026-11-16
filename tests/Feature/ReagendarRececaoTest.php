<?php

namespace Tests\Feature;

use App\Enums\EstadoConsulta;
use App\Models\Consulta;
use App\Models\Especialidade;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReagendarRececaoTest extends TestCase
{
    use RefreshDatabase;

    private Especialidade $cardiologia;

    private Profissional $medica;

    private Profissional $colega;

    private Paciente $paciente;

    private User $rececionista;

    private Consulta $consulta;

    protected function setUp(): void
    {
        parent::setUp();

        // Quarta-feira, 18 nov. 2026. A médica e o colega atendem às segundas, das 09:00 às 11:00.
        $this->travelTo(CarbonImmutable::parse('2026-11-18 08:00:00'));

        $this->cardiologia = Especialidade::factory()->create();
        $this->medica = Profissional::factory()->comEspecialidade($this->cardiologia)->comHorario(1, '09:00', '11:00')->create();
        $this->colega = Profissional::factory()->comEspecialidade($this->cardiologia)->comHorario(1, '09:00', '11:00')->create();
        $this->paciente = Paciente::factory()->create();
        $this->rececionista = User::factory()->rececionista()->create();
        $this->consulta = Consulta::factory()->confirmada()->for($this->paciente)->for($this->medica)
            ->create(['inicio' => '2026-11-23 09:30:00', 'motivo_consulta' => 'Dores no peito']);
    }

    /** @param  array<string, mixed>  $alteracoes */
    private function reagendar(Consulta $consulta, array $alteracoes = []): TestResponse
    {
        return $this->actingAs($this->rececionista)->post(route('rececao.consultas.reagendar', $consulta), $alteracoes + [
            'profissional_id' => $this->medica->id,
            'inicio' => '2026-11-23T10:30',
        ]);
    }

    public function test_lista_as_consultas_futuras_e_filtra_por_profissional_e_por_data(): void
    {
        Consulta::factory()->confirmada()->for($this->colega)->create(['inicio' => '2026-11-30 10:00:00']);
        Consulta::factory()->realizada()->for($this->medica)->create(['inicio' => '2026-11-16 09:00:00']);

        $this->actingAs($this->rececionista)->get(route('rececao.consultas.index'))
            ->assertOk()
            ->assertSeeInOrder(['23/11/2026 09:30', $this->paciente->nome, '30/11/2026 10:00'])
            ->assertDontSee('16/11/2026 09:00');

        $this->actingAs($this->rececionista)->get(route('rececao.consultas.index', ['profissional_id' => $this->colega->id]))
            ->assertSee('30/11/2026 10:00')->assertDontSee('23/11/2026 09:30');

        $this->actingAs($this->rececionista)->get(route('rececao.consultas.index', ['de' => '2026-11-16']))
            ->assertSee('16/11/2026 09:00');
    }

    public function test_reagendar_cancela_a_antiga_e_cria_uma_nova_ligada_e_confirmada(): void
    {
        $this->reagendar($this->consulta)
            ->assertRedirect(route('rececao.consultas.index'))
            ->assertSessionHas('success');

        $antiga = $this->consulta->fresh();
        $nova = $antiga->reagendamento;

        $this->assertSame(EstadoConsulta::Cancelada, $antiga->estado);
        $this->assertSame('Reagendada', $antiga->historico()->get()->last()->motivo);
        $this->assertSame($this->rececionista->id, $antiga->historico()->get()->last()->user_id);

        $this->assertSame('2026-11-23 10:30:00', $nova->inicio->toDateTimeString());
        $this->assertSame(EstadoConsulta::Confirmada, $nova->estado);
        $this->assertSame($this->paciente->id, $nova->paciente_id);
        $this->assertSame($this->cardiologia->id, $nova->especialidade_id);
        $this->assertSame('Dores no peito', $nova->motivo_consulta);
        $this->assertSame(
            [EstadoConsulta::Pedida, EstadoConsulta::Confirmada],
            $nova->historico()->get()->pluck('estado')->all(),
        );
    }

    public function test_pedido_pendente_reagendado_continua_pendente(): void
    {
        $pedido = Consulta::factory()->pedida()->for($this->medica)->create(['inicio' => '2026-11-23 10:00:00']);

        $this->reagendar($pedido)->assertSessionHasNoErrors();

        $this->assertSame(EstadoConsulta::Cancelada, $pedido->fresh()->estado);
        $this->assertSame(EstadoConsulta::Pedida, $pedido->reagendamento->estado);
    }

    public function test_reagendar_para_outro_profissional_da_mesma_especialidade_a_mesma_hora(): void
    {
        $this->reagendar($this->consulta, ['profissional_id' => $this->colega->id, 'inicio' => '2026-11-23T09:30'])
            ->assertSessionHasNoErrors();

        $nova = $this->consulta->fresh()->reagendamento;
        $this->assertSame($this->colega->id, $nova->profissional_id);
        $this->assertSame('2026-11-23 09:30:00', $nova->inicio->toDateTimeString());
    }

    public function test_a_vaga_antiga_fica_livre_e_a_lista_de_espera_e_promovida(): void
    {
        $emEspera = Consulta::factory()->emEspera()->for($this->medica)->create(['inicio' => '2026-11-23 09:30:00']);

        $this->reagendar($this->consulta)->assertSessionHasNoErrors();

        $this->assertSame(EstadoConsulta::Pedida, $emEspera->fresh()->estado);
    }

    public function test_recusa_vaga_ocupada_fora_do_horario_ou_de_outra_especialidade_sem_mexer_na_consulta(): void
    {
        Consulta::factory()->confirmada()->for($this->medica)->create(['inicio' => '2026-11-23 10:00:00']);
        $outro = Profissional::factory()->comEspecialidade()->comHorario(1, '09:00', '11:00')->create();

        $this->reagendar($this->consulta, ['inicio' => '2026-11-23T10:00'])->assertSessionHasErrors('inicio');
        $this->reagendar($this->consulta, ['inicio' => '2026-11-23T14:00'])->assertSessionHasErrors('inicio');
        $this->reagendar($this->consulta, ['inicio' => '2026-11-23T09:30'])->assertSessionHasErrors('inicio');
        $this->reagendar($this->consulta, ['profissional_id' => $outro->id])->assertSessionHasErrors('especialidade_id');
        $this->reagendar($this->consulta, ['inicio' => 'amanhã'])->assertSessionHasErrors('inicio');

        $this->assertSame(EstadoConsulta::Confirmada, $this->consulta->fresh()->estado);
        $this->assertNull($this->consulta->fresh()->reagendamento);
    }

    public function test_conflito_do_paciente_noutra_consulta_desfaz_tudo(): void
    {
        // O paciente já tem outra consulta com o colega à hora para onde se quer reagendar.
        Consulta::factory()->confirmada()->for($this->paciente)->for($this->colega)->create(['inicio' => '2026-11-23 10:30:00']);

        $this->reagendar($this->consulta)->assertSessionHasErrors('inicio');

        $this->assertSame(EstadoConsulta::Confirmada, $this->consulta->fresh()->estado);
        $this->assertSame(2, $this->consulta->historico()->count());
        $this->assertSame(2, Consulta::count());
    }

    public function test_nao_se_reagendam_consultas_terminadas_nem_que_ja_comecaram(): void
    {
        $cancelada = Consulta::factory()->cancelada()->for($this->medica)->create(['inicio' => '2026-11-23 10:00:00']);
        $jaComecou = Consulta::factory()->confirmada()->for($this->medica)->create(['inicio' => '2026-11-16 09:00:00']);

        foreach ([$cancelada, $jaComecou] as $consulta) {
            $this->actingAs($this->rececionista)
                ->postJson(route('rececao.consultas.reagendar', $consulta), ['profissional_id' => $this->medica->id, 'inicio' => '2026-11-23T10:30'])
                ->assertStatus(409);

            $this->actingAs($this->rececionista)->get(route('rececao.consultas.reagendamento', $consulta))
                ->assertRedirect(route('rececao.consultas.index'))->assertSessionHas('error');
        }

        $this->assertSame(3, Consulta::count());
    }

    public function test_rececao_cancela_com_motivo_obrigatorio(): void
    {
        $this->actingAs($this->rececionista)
            ->post(route('rececao.consultas.cancelar', $this->consulta))
            ->assertSessionHasErrors('motivo');
        $this->assertSame(EstadoConsulta::Confirmada, $this->consulta->fresh()->estado);

        $this->actingAs($this->rececionista)
            ->from(route('rececao.consultas.index'))
            ->post(route('rececao.consultas.cancelar', $this->consulta), ['motivo' => 'Pedido por telefone'])
            ->assertRedirect(route('rececao.consultas.index'))
            ->assertSessionHas('success');

        $ultimo = $this->consulta->fresh()->historico()->get()->last();
        $this->assertSame(EstadoConsulta::Cancelada, $ultimo->estado);
        $this->assertSame('Pedido por telefone', $ultimo->motivo);

        $this->actingAs($this->rececionista)
            ->postJson(route('rececao.consultas.cancelar', $this->consulta), ['motivo' => 'Outra vez'])
            ->assertStatus(409);
    }

    public function test_pagina_de_reagendar_mostra_a_consulta_e_a_lista_tem_as_acoes_certas(): void
    {
        $realizada = Consulta::factory()->realizada()->for($this->medica)->create(['inicio' => '2026-11-16 09:00:00']);

        $this->actingAs($this->rececionista)->get(route('rececao.consultas.reagendamento', $this->consulta))
            ->assertOk()->assertSee($this->paciente->nome)->assertSee('23/11/2026 09:30');

        $this->actingAs($this->rececionista)->get(route('rececao.consultas.index', ['de' => '2026-11-16']))
            ->assertSee(route('rececao.consultas.reagendamento', $this->consulta))
            ->assertSee(route('rececao.consultas.cancelar', $this->consulta))
            ->assertDontSee(route('rececao.consultas.reagendamento', $realizada))
            ->assertDontSee(route('rececao.consultas.cancelar', $realizada));
    }

    public function test_so_a_rececao_ve_cancela_e_reagenda_por_estas_rotas(): void
    {
        $this->get(route('rececao.consultas.index'))->assertRedirect(route('login'));

        foreach ([User::factory()->administrador()->create(), $this->paciente->user, $this->medica->user] as $user) {
            $this->actingAs($user)->get(route('rececao.consultas.index'))->assertForbidden();
            $this->actingAs($user)->get(route('rececao.consultas.reagendamento', $this->consulta))->assertForbidden();
            $this->actingAs($user)->post(route('rececao.consultas.reagendar', $this->consulta), ['profissional_id' => $this->medica->id, 'inicio' => '2026-11-23T10:30'])->assertForbidden();
            $this->actingAs($user)->post(route('rececao.consultas.cancelar', $this->consulta), ['motivo' => 'x'])->assertForbidden();
        }

        $this->assertSame(EstadoConsulta::Confirmada, $this->consulta->fresh()->estado);
        $this->assertSame(1, Consulta::count());
    }

    public function test_paginas_em_ingles(): void
    {
        $this->actingAs($this->rececionista)->withSession(['locale' => 'en'])
            ->get(route('rececao.consultas.index'))
            ->assertSee('Appointments')->assertSee('Reschedule')->assertSee('Confirm cancellation');
    }
}
