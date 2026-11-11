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

class RegistoConsultaTest extends TestCase
{
    use RefreshDatabase;

    private Perfil $perfilProfissional;

    private Profissional $medica;

    private Especialidade $cardiologia;

    private int $utente = 100000000;

    protected function setUp(): void
    {
        parent::setUp();

        // Quarta-feira, 18 nov. 2026, 11:00.
        $this->travelTo(CarbonImmutable::parse('2026-11-18 11:00:00'));

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

    private function confirmada(string $inicio, ?Profissional $de = null, bool $confirmar = true): Consulta
    {
        $paciente = Paciente::create(['nome' => 'Paciente '.$this->utente, 'num_utente' => (string) $this->utente++]);
        $consulta = Consulta::registarPedido([
            'paciente_id' => $paciente->id,
            'profissional_id' => ($de ?? $this->medica)->id,
            'especialidade_id' => $this->cardiologia->id,
            'inicio' => $inicio,
        ], EstadoConsulta::Pedida, null);

        if ($confirmar) {
            $consulta->mudarEstado(EstadoConsulta::Confirmada, null);
        }

        return $consulta;
    }

    public function test_regista_realizada_depois_da_hora_com_o_profissional_no_historico(): void
    {
        $consulta = $this->confirmada('2026-11-18 10:00:00');

        $this->actingAs($this->medica->user)
            ->from(route('profissional.agenda.index'))
            ->post(route('profissional.consultas.realizada', $consulta))
            ->assertRedirect(route('profissional.agenda.index'))
            ->assertSessionHas('success');

        $consulta->refresh();
        $this->assertSame(EstadoConsulta::Realizada, $consulta->estado);
        $this->assertSame($this->medica->user_id, $consulta->historico()->get()->last()->user_id);
    }

    public function test_regista_falta(): void
    {
        $consulta = $this->confirmada('2026-11-18 10:00:00');

        $this->actingAs($this->medica->user)->post(route('profissional.consultas.faltou', $consulta))
            ->assertSessionHas('success');

        $this->assertSame(EstadoConsulta::Faltou, $consulta->fresh()->estado);
    }

    public function test_nao_regista_antes_da_hora_de_inicio(): void
    {
        $consulta = $this->confirmada('2026-11-18 11:30:00');

        $this->actingAs($this->medica->user)->post(route('profissional.consultas.realizada', $consulta))
            ->assertSessionHas('error');

        $this->assertSame(EstadoConsulta::Confirmada, $consulta->fresh()->estado);
    }

    public function test_nao_regista_consulta_que_nao_esta_confirmada(): void
    {
        $pedida = $this->confirmada('2026-11-18 09:00:00', confirmar: false);
        $realizada = $this->confirmada('2026-11-18 09:30:00');
        $realizada->mudarEstado(EstadoConsulta::Realizada, null);

        $this->actingAs($this->medica->user)->post(route('profissional.consultas.realizada', $pedida))->assertSessionHas('error');
        $this->actingAs($this->medica->user)->post(route('profissional.consultas.faltou', $realizada))->assertSessionHas('error');

        $this->assertSame(EstadoConsulta::Pedida, $pedida->fresh()->estado);
        $this->assertSame(EstadoConsulta::Realizada, $realizada->fresh()->estado);
    }

    public function test_nao_regista_consulta_de_outro_profissional(): void
    {
        $consulta = $this->confirmada('2026-11-18 10:00:00', $this->criarProfissional('2'));

        $this->actingAs($this->medica->user)->post(route('profissional.consultas.realizada', $consulta))->assertForbidden();

        $this->assertSame(EstadoConsulta::Confirmada, $consulta->fresh()->estado);
    }

    public function test_agenda_so_mostra_os_botoes_em_consultas_confirmadas_que_ja_comecaram(): void
    {
        $passada = $this->confirmada('2026-11-18 10:00:00');
        $futura = $this->confirmada('2026-11-18 15:00:00');

        $this->actingAs($this->medica->user)->get(route('profissional.agenda.index'))
            ->assertOk()
            ->assertSee(route('profissional.consultas.realizada', $passada), false)
            ->assertDontSee(route('profissional.consultas.realizada', $futura), false);
    }

    public function test_outros_perfis_nao_registam(): void
    {
        $consulta = $this->confirmada('2026-11-18 10:00:00');
        $rececionista = User::factory()->create(['perfil_id' => Perfil::create(['nome' => 'rececionista'])->id]);

        $this->actingAs($rececionista)->post(route('profissional.consultas.realizada', $consulta))->assertForbidden();

        $this->assertSame(EstadoConsulta::Confirmada, $consulta->fresh()->estado);
    }
}
