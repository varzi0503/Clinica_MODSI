<?php

namespace Tests\Feature;

use App\Enums\EstadoConsulta;
use App\Models\Consulta;
use App\Models\Especialidade;
use App\Models\Paciente;
use App\Models\Perfil;
use App\Models\Profissional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PedidosRececaoTest extends TestCase
{
    use RefreshDatabase;

    private User $rececionista;

    private Profissional $profissional;

    private Especialidade $especialidade;

    private Paciente $paciente;

    protected function setUp(): void
    {
        parent::setUp();

        $perfilRececionista = Perfil::create(['nome' => 'rececionista']);
        $perfilProfissional = Perfil::create(['nome' => 'profissional']);
        Perfil::create(['nome' => 'paciente']);

        $this->rececionista = User::factory()->create(['perfil_id' => $perfilRececionista->id]);

        $user = User::factory()->create(['perfil_id' => $perfilProfissional->id]);
        $this->profissional = Profissional::create(['user_id' => $user->id, 'tipo' => 'medico', 'num_cedula' => '12345']);
        $this->especialidade = Especialidade::create(['nome' => 'Cardiologia']);
        $this->profissional->especialidades()->attach($this->especialidade->id);
        $this->paciente = Paciente::create(['nome' => 'Augusta Teste', 'num_utente' => '123456789']);
    }

    private function pedido(EstadoConsulta $estado = EstadoConsulta::Pedida, string $inicio = '2026-11-20 10:00:00'): Consulta
    {
        return Consulta::registarPedido([
            'paciente_id' => $this->paciente->id,
            'profissional_id' => $this->profissional->id,
            'especialidade_id' => $this->especialidade->id,
            'inicio' => $inicio,
            'motivo_consulta' => 'Dores no peito',
        ], $estado, null);
    }

    public function test_lista_mostra_pedidos_pendentes_e_ignora_os_em_espera(): void
    {
        $this->pedido();
        $this->pedido(EstadoConsulta::EmEspera, '2026-11-21 11:00:00');

        $resposta = $this->actingAs($this->rececionista)->get(route('rececao.pedidos.index'));

        $resposta->assertOk()
            ->assertSee('Augusta Teste')
            ->assertSee('20/11/2026 10:00')
            ->assertDontSee('21/11/2026 11:00');
    }

    public function test_confirmar_muda_o_estado_e_regista_a_rececionista_no_historico(): void
    {
        $consulta = $this->pedido();

        $this->actingAs($this->rececionista)
            ->post(route('rececao.pedidos.confirmar', $consulta))
            ->assertRedirect(route('rececao.pedidos.index'));

        $consulta->refresh();
        $this->assertSame(EstadoConsulta::Confirmada, $consulta->estado);
        $this->assertSame($this->rececionista->id, $consulta->historico()->get()->last()->user_id);
    }

    public function test_rejeitar_exige_motivo_e_guarda_o_no_historico(): void
    {
        $consulta = $this->pedido();

        $this->actingAs($this->rececionista)
            ->post(route('rececao.pedidos.rejeitar', $consulta))
            ->assertSessionHasErrors('motivo');

        $this->assertSame(EstadoConsulta::Pedida, $consulta->fresh()->estado);

        $this->actingAs($this->rececionista)
            ->post(route('rececao.pedidos.rejeitar', $consulta), ['motivo' => 'Sem vaga real'])
            ->assertRedirect(route('rececao.pedidos.index'));

        $consulta->refresh();
        $this->assertSame(EstadoConsulta::Rejeitada, $consulta->estado);
        $this->assertSame('Sem vaga real', $consulta->historico()->get()->last()->motivo);
    }

    public function test_pedido_ja_tratado_devolve_erro_e_nao_muda_nada(): void
    {
        $consulta = $this->pedido();
        $consulta->mudarEstado(EstadoConsulta::Confirmada, $this->rececionista);

        $this->actingAs($this->rececionista)
            ->post(route('rececao.pedidos.confirmar', $consulta))
            ->assertRedirect(route('rececao.pedidos.index'))
            ->assertSessionHas('error');

        $this->assertSame(EstadoConsulta::Confirmada, $consulta->fresh()->estado);
        $this->assertSame(2, $consulta->historico()->count());
    }

    public function test_outros_perfis_nao_acedem(): void
    {
        $consulta = $this->pedido();

        $this->get(route('rececao.pedidos.index'))->assertRedirect(route('login'));

        $profissional = $this->profissional->user;
        $this->actingAs($profissional)->get(route('rececao.pedidos.index'))->assertForbidden();
        $this->actingAs($profissional)->post(route('rececao.pedidos.confirmar', $consulta))->assertForbidden();

        $this->assertSame(EstadoConsulta::Pedida, $consulta->fresh()->estado);
    }
}
