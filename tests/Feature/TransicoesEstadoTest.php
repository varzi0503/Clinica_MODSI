<?php

namespace Tests\Feature;

use App\Enums\EstadoConsulta;
use App\Exceptions\TransicaoEstadoInvalida;
use App\Models\Consulta;
use App\Models\Especialidade;
use App\Models\Paciente;
use App\Models\Perfil;
use App\Models\Profissional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransicoesEstadoTest extends TestCase
{
    use RefreshDatabase;

    /** A matriz completa das transições permitidas (issue #61). */
    private const PERMITIDAS = [
        'em_espera' => ['pedida', 'cancelada'],
        'pedida' => ['confirmada', 'rejeitada', 'cancelada'],
        'confirmada' => ['realizada', 'faltou', 'cancelada'],
        'rejeitada' => [],
        'cancelada' => [],
        'realizada' => [],
        'faltou' => [],
    ];

    private Profissional $profissional;

    private Especialidade $especialidade;

    private Paciente $paciente;

    protected function setUp(): void
    {
        parent::setUp();

        $perfil = Perfil::create(['nome' => 'profissional']);
        $user = User::factory()->create(['perfil_id' => $perfil->id]);
        $this->profissional = Profissional::create(['user_id' => $user->id, 'tipo' => 'medico', 'num_cedula' => '12345']);
        $this->especialidade = Especialidade::create(['nome' => 'Cardiologia']);
        $this->profissional->especialidades()->attach($this->especialidade->id);
        $this->paciente = Paciente::create(['nome' => 'Paciente Teste', 'num_utente' => '123456789']);
    }

    private function consultaEm(EstadoConsulta $estado): Consulta
    {
        // Entra diretamente no estado pedido, sem passar pelo fluxo normal,
        // porque aqui testamos a matriz e não o caminho até lá.
        $consulta = new Consulta([
            'paciente_id' => $this->paciente->id,
            'profissional_id' => $this->profissional->id,
            'especialidade_id' => $this->especialidade->id,
            'inicio' => '2026-11-20 10:00:00',
        ]);
        $consulta->estado = $estado;
        $consulta->data_pedido = $consulta->data_estado = now();
        $consulta->save();

        return $consulta;
    }

    public function test_a_matriz_do_enum_corresponde_a_esperada(): void
    {
        foreach (EstadoConsulta::cases() as $de) {
            $esperadas = self::PERMITIDAS[$de->value];
            $obtidas = array_map(fn (EstadoConsulta $e) => $e->value, $de->transicoesPermitidas());

            sort($esperadas);
            sort($obtidas);
            $this->assertSame($esperadas, $obtidas, "Transições erradas a partir de {$de->value}");
        }
    }

    public function test_mudar_estado_recusa_todas_as_transicoes_fora_da_matriz(): void
    {
        foreach (EstadoConsulta::cases() as $de) {
            foreach (EstadoConsulta::cases() as $para) {
                if (in_array($para->value, self::PERMITIDAS[$de->value], true)) {
                    continue;
                }

                $consulta = $this->consultaEm($de);

                try {
                    $consulta->mudarEstado($para, null);
                    $this->fail("Transição {$de->value} -> {$para->value} devia ter sido recusada");
                } catch (TransicaoEstadoInvalida $e) {
                    $this->assertSame($de, $e->de);
                    $this->assertSame($para, $e->para);
                }

                // Nada mudou nem ficou no histórico.
                $this->assertSame($de, $consulta->fresh()->estado);
                $this->assertSame(0, $consulta->historico()->count());

                $consulta->historico()->delete();
                $consulta->delete();
            }
        }
    }

    public function test_o_ciclo_de_vida_completo_passa(): void
    {
        $consulta = Consulta::registarPedido([
            'paciente_id' => $this->paciente->id,
            'profissional_id' => $this->profissional->id,
            'especialidade_id' => $this->especialidade->id,
            'inicio' => '2026-11-20 10:00:00',
        ], EstadoConsulta::Pedida, null);

        $consulta->mudarEstado(EstadoConsulta::Confirmada, null);
        $consulta->mudarEstado(EstadoConsulta::Realizada, null);

        $this->assertSame(EstadoConsulta::Realizada, $consulta->fresh()->estado);
        $this->assertSame(3, $consulta->historico()->count());
    }

    public function test_promocao_da_lista_de_espera_passa(): void
    {
        $consulta = $this->consultaEm(EstadoConsulta::EmEspera);

        $consulta->mudarEstado(EstadoConsulta::Pedida, null, 'promoção automática');

        $this->assertSame(EstadoConsulta::Pedida, $consulta->fresh()->estado);
    }

    public function test_registar_pedido_so_aceita_pedida_ou_em_espera(): void
    {
        $this->expectException(TransicaoEstadoInvalida::class);

        Consulta::registarPedido([
            'paciente_id' => $this->paciente->id,
            'profissional_id' => $this->profissional->id,
            'especialidade_id' => $this->especialidade->id,
            'inicio' => '2026-11-20 10:00:00',
        ], EstadoConsulta::Confirmada, null);
    }
}
