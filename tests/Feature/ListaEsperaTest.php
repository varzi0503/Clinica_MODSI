<?php

namespace Tests\Feature;

use App\Enums\EstadoConsulta;
use App\Models\Consulta;
use App\Models\Especialidade;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\User;
use App\Services\Marcacoes;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListaEsperaTest extends TestCase
{
    use RefreshDatabase;

    private const VAGA = '2026-11-23 09:30:00';

    private Especialidade $cardiologia;

    private Profissional $medica;

    private Paciente $paciente;

    private User $rececionista;

    private Consulta $ocupante;

    protected function setUp(): void
    {
        parent::setUp();

        // Quarta-feira, 18 nov. 2026. A médica atende às segundas, das 09:00 às 11:00,
        // e a vaga de segunda 23, às 09:30, já tem uma consulta confirmada.
        $this->travelTo(CarbonImmutable::parse('2026-11-18 08:00:00'));

        $this->cardiologia = Especialidade::factory()->create();
        $this->medica = Profissional::factory()->comEspecialidade($this->cardiologia)->comHorario(1, '09:00', '11:00')->create();
        $this->paciente = Paciente::factory()->create();
        $this->rececionista = User::factory()->rececionista()->create();
        $this->ocupante = Consulta::factory()->confirmada()->for($this->medica)->create(['inicio' => self::VAGA]);
    }

    /** @return array<string, mixed> */
    private function pedido(array $alteracoes = []): array
    {
        return $alteracoes + [
            'especialidade_id' => $this->cardiologia->id,
            'profissional_id' => $this->medica->id,
            'inicio' => '2026-11-23T09:30',
            'lista_espera' => 1,
        ];
    }

    private function emEspera(?Paciente $paciente = null): Consulta
    {
        return app(Marcacoes::class)->pedir(
            $paciente ?? Paciente::factory()->create(), $this->medica, $this->cardiologia->id,
            CarbonImmutable::parse(self::VAGA), null, $this->rececionista, aceitaEspera: true,
        );
    }

    public function test_paciente_pede_vaga_ocupada_e_fica_em_espera_sem_ocupar_a_vaga(): void
    {
        $this->actingAs($this->paciente->user)
            ->post(route('paciente.pedidos.store'), $this->pedido())
            ->assertRedirect(route('paciente.pedidos.create'))
            ->assertSessionHas('success');

        $pedido = $this->paciente->consultas()->sole();
        $this->assertSame(EstadoConsulta::EmEspera, $pedido->estado);
        $this->assertSame(EstadoConsulta::Confirmada, $this->ocupante->fresh()->estado);

        $this->actingAs($this->paciente->user)->get(route('paciente.consultas.index'))->assertSee('Em espera');
    }

    public function test_sem_aceitar_a_lista_de_espera_a_vaga_ocupada_e_recusada(): void
    {
        $this->actingAs($this->paciente->user)
            ->post(route('paciente.pedidos.store'), $this->pedido(['lista_espera' => 0]))
            ->assertSessionHasErrors('inicio');

        $this->assertSame(0, $this->paciente->consultas()->count());
    }

    public function test_aceitar_a_lista_de_espera_numa_vaga_livre_fica_com_a_vaga(): void
    {
        $this->actingAs($this->paciente->user)
            ->post(route('paciente.pedidos.store'), $this->pedido(['inicio' => '2026-11-23T10:00']))
            ->assertSessionHasNoErrors();

        $this->assertSame(EstadoConsulta::Pedida, $this->paciente->consultas()->sole()->estado);
    }

    public function test_nao_se_fica_em_espera_fora_do_horario_nem_numa_vaga_passada(): void
    {
        foreach (['2026-11-23T14:00', '2026-11-16T09:30'] as $inicio) {
            $this->actingAs($this->paciente->user)
                ->post(route('paciente.pedidos.store'), $this->pedido(['inicio' => $inicio]))
                ->assertSessionHasErrors('inicio');
        }

        $this->assertSame(0, $this->paciente->consultas()->count());
    }

    public function test_um_so_pedido_em_espera_por_paciente_e_por_vaga(): void
    {
        $this->actingAs($this->paciente->user)->post(route('paciente.pedidos.store'), $this->pedido())->assertSessionHasNoErrors();
        $this->actingAs($this->paciente->user)->post(route('paciente.pedidos.store'), $this->pedido())->assertSessionHasErrors('inicio');

        $this->assertSame(1, $this->paciente->consultas()->count());
    }

    public function test_quem_ja_tem_consulta_a_essa_hora_nao_entra_em_espera(): void
    {
        $this->actingAs($this->ocupante->paciente->user)
            ->post(route('paciente.pedidos.store'), $this->pedido())
            ->assertSessionHasErrors('inicio');

        $this->assertSame(1, $this->ocupante->paciente->consultas()->count());
    }

    public function test_rececao_poe_um_paciente_em_espera(): void
    {
        $this->actingAs($this->rececionista)
            ->post(route('rececao.consultas.store'), $this->pedido(['paciente_id' => $this->paciente->id]))
            ->assertRedirect(route('rececao.consultas.create'))
            ->assertSessionHas('success');

        $pedido = $this->paciente->consultas()->sole();
        $this->assertSame(EstadoConsulta::EmEspera, $pedido->estado);
        $this->assertSame($this->rececionista->id, $pedido->historico()->sole()->user_id);
    }

    public function test_cancelar_promove_o_primeiro_da_lista_em_nome_do_sistema(): void
    {
        $primeiro = $this->emEspera();
        $this->travelTo(now()->addMinute());
        $segundo = $this->emEspera();

        $this->actingAs($this->ocupante->paciente->user)
            ->post(route('paciente.consultas.cancelar', $this->ocupante))
            ->assertSessionHas('success');

        $this->assertSame(EstadoConsulta::Cancelada, $this->ocupante->fresh()->estado);
        $this->assertSame(EstadoConsulta::Pedida, $primeiro->fresh()->estado);
        $this->assertSame(EstadoConsulta::EmEspera, $segundo->fresh()->estado);

        $promocao = $primeiro->historico()->get()->last();
        $this->assertSame(EstadoConsulta::Pedida, $promocao->estado);
        $this->assertNull($promocao->user_id);

        // O promovido aparece agora na lista de pedidos da receção.
        $this->actingAs($this->rececionista)->get(route('rececao.pedidos.index'))->assertSee($primeiro->paciente->nome);
    }

    public function test_cancelar_pelo_profissional_tambem_promove(): void
    {
        $emEspera = $this->emEspera();

        $this->actingAs($this->medica->user)
            ->post(route('profissional.consultas.cancelar', $this->ocupante), ['motivo' => 'Doença'])
            ->assertSessionHas('success');

        $this->assertSame(EstadoConsulta::Pedida, $emEspera->fresh()->estado);
    }

    public function test_rejeitar_um_pedido_promove_a_lista_de_espera(): void
    {
        $this->ocupante->delete();
        $pedido = Consulta::factory()->pedida()->for($this->medica)->create(['inicio' => self::VAGA]);
        $emEspera = $this->emEspera();

        $this->actingAs($this->rececionista)
            ->post(route('rececao.pedidos.rejeitar', $pedido), ['motivo' => 'Sem vaga real'])
            ->assertRedirect(route('rececao.pedidos.index'));

        $this->assertSame(EstadoConsulta::Rejeitada, $pedido->fresh()->estado);
        $this->assertSame(EstadoConsulta::Pedida, $emEspera->fresh()->estado);
    }

    public function test_quem_ja_tem_outra_consulta_a_essa_hora_e_saltado_na_promocao(): void
    {
        $ocupado = Paciente::factory()->create();
        $primeiro = $this->emEspera($ocupado);
        $this->travelTo(now()->addMinute());
        $segundo = $this->emEspera();

        // Depois de entrar em espera, o primeiro ficou com uma consulta à mesma hora com outro profissional.
        $outro = Profissional::factory()->comEspecialidade($this->cardiologia)->create();
        Consulta::factory()->confirmada()->for($ocupado)->for($outro)->create(['inicio' => self::VAGA]);

        app(Marcacoes::class)->cancelar($this->ocupante, $this->rececionista, 'Teste');

        $this->assertSame(EstadoConsulta::Cancelada, $this->ocupante->fresh()->estado);
        $this->assertSame(EstadoConsulta::EmEspera, $primeiro->fresh()->estado);
        $this->assertSame(1, $primeiro->historico()->count());
        $this->assertSame(EstadoConsulta::Pedida, $segundo->fresh()->estado);
    }

    public function test_sair_da_lista_de_espera_nao_promove_ninguem(): void
    {
        $primeiro = $this->emEspera($this->paciente);
        $segundo = $this->emEspera();

        $this->actingAs($this->paciente->user)->post(route('paciente.consultas.cancelar', $primeiro))->assertSessionHas('success');

        $this->assertSame(EstadoConsulta::Cancelada, $primeiro->fresh()->estado);
        $this->assertSame(EstadoConsulta::EmEspera, $segundo->fresh()->estado);
        $this->assertSame(EstadoConsulta::Confirmada, $this->ocupante->fresh()->estado);
    }

    public function test_vaga_que_ja_passou_nao_e_oferecida_e_o_comando_fecha_os_pedidos(): void
    {
        $emEspera = $this->emEspera();
        $futuro = Consulta::factory()->emEspera()->for($this->medica)->create(['inicio' => '2026-11-30 09:30:00']);

        // Terça-feira, 24: a vaga de segunda já passou.
        $this->travelTo(CarbonImmutable::parse('2026-11-24 08:00:00'));
        app(Marcacoes::class)->cancelar($this->ocupante, $this->rececionista, 'Teste');
        $this->assertSame(EstadoConsulta::EmEspera, $emEspera->fresh()->estado);

        $this->artisan('consultas:expirar-espera')->assertSuccessful();

        $emEspera->refresh();
        $this->assertSame(EstadoConsulta::Cancelada, $emEspera->estado);
        $this->assertSame('Vaga expirada', $emEspera->historico()->get()->last()->motivo);
        $this->assertNull($emEspera->historico()->get()->last()->user_id);
        $this->assertSame(EstadoConsulta::EmEspera, $futuro->fresh()->estado);
    }

    public function test_remover_um_periodo_do_horario_cancela_os_pedidos_em_espera_desse_periodo(): void
    {
        // Sem consultas a ocupar o período, senão a remoção é recusada.
        $this->ocupante->delete();
        $noPeriodo = Consulta::factory()->emEspera()->for($this->medica)->create(['inicio' => self::VAGA]);
        $foraDoPeriodo = Consulta::factory()->emEspera()->for($this->medica)->create(['inicio' => '2026-11-24 09:30:00']);

        $this->actingAs($this->medica->user)
            ->delete(route('profissional.horario.destroy', $this->medica->horarios()->sole()))
            ->assertSessionHas('success');

        $this->assertSame(EstadoConsulta::Cancelada, $noPeriodo->fresh()->estado);
        $this->assertSame('Horário removido', $noPeriodo->historico()->get()->last()->motivo);
        $this->assertSame(EstadoConsulta::EmEspera, $foraDoPeriodo->fresh()->estado);
    }

    public function test_vagas_ocupadas_so_sao_devolvidas_ao_paciente_e_a_rececao(): void
    {
        $url = route('disponibilidade.vagas', ['profissional_id' => $this->medica->id, 'de' => '2026-11-23', 'dias' => 1]);

        // Visitante e outros perfis: só as vagas livres, como na página pública (D27).
        $this->getJson($url)->assertOk()->assertJsonMissingPath('profissionais.0.ocupadas');
        $this->actingAs($this->medica->user)->getJson($url)->assertJsonMissingPath('profissionais.0.ocupadas');

        foreach ([$this->paciente->user, $this->rececionista] as $user) {
            $this->actingAs($user)->getJson($url)
                ->assertOk()
                ->assertJsonPath('profissionais.0.ocupadas', ['2026-11-23T09:30'])
                ->assertJsonPath('profissionais.0.vagas', ['2026-11-23T09:00', '2026-11-23T10:00', '2026-11-23T10:30']);
        }
    }
}
