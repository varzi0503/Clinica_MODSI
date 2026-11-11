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

class ConsultaModeloTest extends TestCase
{
    use RefreshDatabase;

    private Profissional $profissional;

    private Especialidade $especialidade;

    private Paciente $paciente;

    private User $rececionista;

    protected function setUp(): void
    {
        parent::setUp();

        $perfilProfissional = Perfil::create(['nome' => 'profissional']);
        $perfilRececionista = Perfil::create(['nome' => 'rececionista']);

        $this->rececionista = User::factory()->create(['perfil_id' => $perfilRececionista->id]);

        $user = User::factory()->create(['perfil_id' => $perfilProfissional->id]);
        $this->profissional = Profissional::create([
            'user_id' => $user->id,
            'tipo' => 'medico',
            'num_cedula' => '12345',
        ]);
        $this->especialidade = Especialidade::create(['nome' => 'Cardiologia']);
        $this->profissional->especialidades()->attach($this->especialidade->id);

        $this->paciente = Paciente::create(['nome' => 'Paciente Teste', 'num_utente' => '123456789']);
    }

    private function pedido(EstadoConsulta $estado, ?User $por = null): Consulta
    {
        return Consulta::registarPedido([
            'paciente_id' => $this->paciente->id,
            'profissional_id' => $this->profissional->id,
            'especialidade_id' => $this->especialidade->id,
            'inicio' => '2026-11-20 10:00:00',
        ], $estado, $por);
    }

    public function test_registar_pedido_cria_o_primeiro_registo_do_historico(): void
    {
        $consulta = $this->pedido(EstadoConsulta::Pedida, $this->rececionista);

        $this->assertSame(EstadoConsulta::Pedida, $consulta->estado);
        $this->assertSame(1, $consulta->historico()->count());
        $this->assertSame($this->rececionista->id, $consulta->historico->first()->user_id);
        $this->assertTrue($consulta->data_pedido->equalTo($consulta->data_estado));
    }

    public function test_mudar_estado_regista_no_historico_com_autor_nulo_quando_e_o_sistema(): void
    {
        $consulta = $this->pedido(EstadoConsulta::Pedida, $this->rececionista);

        $consulta->mudarEstado(EstadoConsulta::Cancelada, null, 'promoção automática');

        $historico = $consulta->historico()->get();
        $this->assertCount(2, $historico);
        $this->assertNull($historico->last()->user_id);
        $this->assertSame(EstadoConsulta::Cancelada, $historico->last()->estado);
        $this->assertSame('promoção automática', $historico->last()->motivo);
        $this->assertSame(EstadoConsulta::Cancelada, $consulta->fresh()->estado);
    }

    public function test_estado_da_consulta_nunca_divergir_do_ultimo_historico(): void
    {
        $consulta = $this->pedido(EstadoConsulta::Pedida, $this->rececionista);
        $consulta->mudarEstado(EstadoConsulta::Confirmada, $this->rececionista);
        $consulta->mudarEstado(EstadoConsulta::Realizada, $this->rececionista);

        $this->assertSame(
            $consulta->fresh()->estado,
            $consulta->historico()->get()->last()->estado,
        );
    }

    public function test_scope_em_espera_ordena_por_data_do_pedido(): void
    {
        $this->pedido(EstadoConsulta::Pedida, $this->rececionista);

        $this->travelTo(now()->addMinute());
        $segundo = $this->pedido(EstadoConsulta::EmEspera);

        $this->travelTo(now()->addMinute());
        $this->pedido(EstadoConsulta::EmEspera);

        $this->assertSame($segundo->id, Consulta::emEspera()->first()->id);
    }

    public function test_factory_de_user_cria_perfil_quando_nao_lhe_e_dado_um(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->perfil);
    }
}
