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

/**
 * Padrão AJAX/JSON comum (issue #51), aplicado às ações do UC16:
 * 2xx {message}, 422 validação, 409 conflito de estado, 403 sem acesso.
 */
class PedidosAjaxTest extends TestCase
{
    use RefreshDatabase;

    private User $rececionista;

    private Consulta $pedido;

    protected function setUp(): void
    {
        parent::setUp();

        $perfilRececionista = Perfil::create(['nome' => 'rececionista']);
        $perfilProfissional = Perfil::create(['nome' => 'profissional']);

        $this->rececionista = User::factory()->create(['perfil_id' => $perfilRececionista->id]);

        $user = User::factory()->create(['perfil_id' => $perfilProfissional->id]);
        $profissional = Profissional::create(['user_id' => $user->id, 'tipo' => 'medico', 'num_cedula' => '12345']);
        $especialidade = Especialidade::create(['nome' => 'Cardiologia']);
        $profissional->especialidades()->attach($especialidade->id);
        $paciente = Paciente::create(['nome' => 'Paciente Teste', 'num_utente' => '123456789']);

        $this->pedido = Consulta::registarPedido([
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'inicio' => '2026-11-20 10:00:00',
        ], EstadoConsulta::Pedida, null);
    }

    public function test_confirmar_devolve_json_com_mensagem(): void
    {
        $this->actingAs($this->rececionista)
            ->postJson(route('rececao.pedidos.confirmar', $this->pedido))
            ->assertOk()
            ->assertJsonPath('message', 'Consulta confirmada.');

        $this->assertSame(EstadoConsulta::Confirmada, $this->pedido->fresh()->estado);
    }

    public function test_rejeitar_sem_motivo_devolve_422_com_o_campo(): void
    {
        $this->actingAs($this->rececionista)
            ->postJson(route('rececao.pedidos.rejeitar', $this->pedido))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('motivo');

        $this->assertSame(EstadoConsulta::Pedida, $this->pedido->fresh()->estado);
    }

    public function test_pedido_ja_tratado_devolve_409(): void
    {
        $this->pedido->mudarEstado(EstadoConsulta::Confirmada, $this->rececionista);

        $this->actingAs($this->rececionista)
            ->postJson(route('rececao.pedidos.confirmar', $this->pedido))
            ->assertStatus(409)
            ->assertJsonStructure(['message']);
    }

    public function test_perfil_errado_devolve_403_em_json(): void
    {
        $profissional = User::where('email', '!=', $this->rececionista->email)->first();

        $this->actingAs($profissional)
            ->postJson(route('rececao.pedidos.confirmar', $this->pedido))
            ->assertForbidden();

        $this->assertSame(EstadoConsulta::Pedida, $this->pedido->fresh()->estado);
    }
}
