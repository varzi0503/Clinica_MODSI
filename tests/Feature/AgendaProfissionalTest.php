<?php

namespace Tests\Feature;

use App\Enums\EstadoConsulta;
use App\Models\Consulta;
use App\Models\Especialidade;
use App\Models\Paciente;
use App\Models\Perfil;
use App\Models\Profissional;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaProfissionalTest extends TestCase
{
    use RefreshDatabase;

    private Perfil $perfilProfissional;

    private Profissional $medica;

    private Especialidade $cardiologia;

    private int $utente = 100000000;

    protected function setUp(): void
    {
        parent::setUp();

        // Quarta-feira, 18 nov. 2026: a semana vai de 16 (seg.) a 22 (dom.).
        $this->travelTo(CarbonImmutable::parse('2026-11-18 08:00:00'));

        $this->perfilProfissional = Perfil::create(['nome' => 'profissional']);
        $this->cardiologia = Especialidade::create(['nome' => 'Cardiologia']);
        $this->medica = $this->criarProfissional('1');
    }

    private function criarProfissional(string $cedula): Profissional
    {
        $p = Profissional::create([
            'user_id' => User::factory()->create(['perfil_id' => $this->perfilProfissional->id])->id,
            'tipo' => 'medico', 'num_cedula' => $cedula,
        ]);
        $p->especialidades()->attach($this->cardiologia->id);

        return $p;
    }

    /** Cria uma consulta no estado pedido, passando pelas transições válidas. */
    private function consulta(string $nome, string $inicio, EstadoConsulta $estado, ?Profissional $de = null): Consulta
    {
        $paciente = Paciente::create(['nome' => $nome, 'num_utente' => (string) $this->utente++]);
        $inicial = $estado === EstadoConsulta::EmEspera ? EstadoConsulta::EmEspera : EstadoConsulta::Pedida;

        $consulta = Consulta::registarPedido([
            'paciente_id' => $paciente->id,
            'profissional_id' => ($de ?? $this->medica)->id,
            'especialidade_id' => $this->cardiologia->id,
            'inicio' => $inicio,
        ], $inicial, null);

        $caminho = match ($estado) {
            EstadoConsulta::Confirmada => [EstadoConsulta::Confirmada],
            EstadoConsulta::Realizada => [EstadoConsulta::Confirmada, EstadoConsulta::Realizada],
            EstadoConsulta::Faltou => [EstadoConsulta::Confirmada, EstadoConsulta::Faltou],
            EstadoConsulta::Cancelada => [EstadoConsulta::Cancelada],
            EstadoConsulta::Rejeitada => [EstadoConsulta::Rejeitada],
            default => [],
        };
        foreach ($caminho as $passo) {
            $consulta->mudarEstado($passo, null);
        }

        return $consulta;
    }

    private function agenda(array $params = [])
    {
        return $this->actingAs($this->medica->user)->get(route('profissional.agenda.index', $params));
    }

    public function test_semana_mostra_as_consultas_que_ocupam_vaga(): void
    {
        $this->consulta('Ana Pedida', '2026-11-16 09:00:00', EstadoConsulta::Pedida);
        $this->consulta('Bruno Confirmado', '2026-11-18 10:00:00', EstadoConsulta::Confirmada);
        $this->consulta('Carla Realizada', '2026-11-16 10:00:00', EstadoConsulta::Realizada);
        $this->consulta('Duarte Faltou', '2026-11-17 09:00:00', EstadoConsulta::Faltou);

        $this->agenda()->assertOk()
            ->assertSeeInOrder(['Ana Pedida', 'Carla Realizada', 'Duarte Faltou', 'Bruno Confirmado'])
            ->assertSee(__('Pedida'))->assertSee(__('Faltou'));
    }

    public function test_esconde_canceladas_rejeitadas_e_em_espera(): void
    {
        $this->consulta('Eva Cancelada', '2026-11-18 09:00:00', EstadoConsulta::Cancelada);
        $this->consulta('Filipa Rejeitada', '2026-11-18 09:30:00', EstadoConsulta::Rejeitada);
        $this->consulta('Gil Espera', '2026-11-18 10:30:00', EstadoConsulta::EmEspera);

        $this->agenda()->assertOk()
            ->assertDontSee('Eva Cancelada')->assertDontSee('Filipa Rejeitada')->assertDontSee('Gil Espera');
    }

    public function test_nao_mostra_consultas_de_outros_profissionais(): void
    {
        $this->consulta('Hugo Doutro', '2026-11-18 09:00:00', EstadoConsulta::Confirmada, $this->criarProfissional('2'));

        $this->agenda()->assertOk()->assertDontSee('Hugo Doutro');
    }

    public function test_vista_de_dia_e_navegacao_entre_semanas(): void
    {
        $this->consulta('Ines Segunda', '2026-11-16 09:00:00', EstadoConsulta::Confirmada);
        $this->consulta('Joao Quarta', '2026-11-18 09:00:00', EstadoConsulta::Confirmada);
        $this->consulta('Luis Proxima', '2026-11-23 09:00:00', EstadoConsulta::Confirmada);

        $this->agenda(['vista' => 'dia', 'data' => '2026-11-18'])
            ->assertSee('Joao Quarta')->assertDontSee('Ines Segunda');

        $this->agenda()->assertSee('Ines Segunda')->assertDontSee('Luis Proxima')
            ->assertSee('data=2026-11-25', false);   // ligação para a semana seguinte

        $this->agenda(['data' => '2026-11-25'])->assertSee('Luis Proxima')->assertDontSee('Joao Quarta');
    }

    public function test_parametros_invalidos_sao_recusados(): void
    {
        $this->agenda(['vista' => 'mes'])->assertSessionHasErrors('vista');
        $this->agenda(['data' => '18-11-2026'])->assertSessionHasErrors('data');
    }

    public function test_so_profissionais_com_ficha_veem_a_agenda(): void
    {
        $this->get(route('profissional.agenda.index'))->assertRedirect(route('login'));

        $paciente = User::factory()->create(['perfil_id' => Perfil::create(['nome' => 'paciente'])->id]);
        $this->actingAs($paciente)->get(route('profissional.agenda.index'))->assertForbidden();

        $semFicha = User::factory()->create(['perfil_id' => $this->perfilProfissional->id]);
        $this->actingAs($semFicha)->get(route('profissional.agenda.index'))
            ->assertRedirect(route('home'))->assertSessionHas('error');
    }
}
