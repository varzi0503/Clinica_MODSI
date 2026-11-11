<?php

namespace Tests\Feature;

use App\Enums\EstadoConsulta;
use App\Models\Consulta;
use App\Models\Especialidade;
use App\Models\Horario;
use App\Models\Paciente;
use App\Models\Perfil;
use App\Models\Profissional;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HorarioProfissionalTest extends TestCase
{
    use RefreshDatabase;

    private Perfil $perfilProfissional;

    private Profissional $medica;

    protected function setUp(): void
    {
        parent::setUp();

        // Domingo, 15 nov. 2026: a segunda-feira seguinte (16) está no futuro.
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00'));

        $this->perfilProfissional = Perfil::create(['nome' => 'profissional']);
        $this->medica = $this->criarProfissional('1');
    }

    private function criarProfissional(string $cedula): Profissional
    {
        return Profissional::create([
            'user_id' => User::factory()->create(['perfil_id' => $this->perfilProfissional->id])->id,
            'tipo' => 'medico', 'num_cedula' => $cedula,
        ]);
    }

    private function adicionar(array $dados, ?Profissional $como = null)
    {
        return $this->actingAs(($como ?? $this->medica)->user)
            ->post(route('profissional.horario.store'), $dados + ['dia_semana' => 1]);
    }

    public function test_adiciona_periodo_e_mostra_o_no_horario(): void
    {
        $this->adicionar(['hora_inicio' => '09:00', 'hora_fim' => '12:30'])
            ->assertRedirect(route('profissional.horario.index'))
            ->assertSessionHas('success');

        $horario = $this->medica->horarios()->sole();
        $this->assertSame(1, $horario->dia_semana);
        $this->assertSame('09:00', substr($horario->hora_inicio, 0, 5));
        $this->assertSame('12:30', substr($horario->hora_fim, 0, 5));

        $this->actingAs($this->medica->user)->get(route('profissional.horario.index'))
            ->assertOk()->assertSee('09:00–12:30')->assertSee(__('Segunda-feira'));
    }

    public function test_recusa_periodos_invalidos(): void
    {
        $this->adicionar(['hora_inicio' => '12:00', 'hora_fim' => '09:00'])->assertSessionHasErrors('hora_fim');
        $this->adicionar(['hora_inicio' => '09:00', 'hora_fim' => '09:00'])->assertSessionHasErrors('hora_fim');
        $this->adicionar(['hora_inicio' => '09:15', 'hora_fim' => '10:00'])->assertSessionHasErrors('hora_inicio');
        $this->adicionar(['hora_inicio' => '09:00', 'hora_fim' => '10:00', 'dia_semana' => 8])->assertSessionHasErrors('dia_semana');

        $this->assertSame(0, Horario::count());
    }

    public function test_recusa_sobreposicao_mas_aceita_periodos_encostados(): void
    {
        $this->adicionar(['hora_inicio' => '09:00', 'hora_fim' => '12:00'])->assertSessionHasNoErrors();

        $this->adicionar(['hora_inicio' => '11:00', 'hora_fim' => '13:00'])->assertSessionHasErrors('hora_inicio');
        $this->adicionar(['hora_inicio' => '08:00', 'hora_fim' => '13:00'])->assertSessionHasErrors('hora_inicio');
        $this->adicionar(['hora_inicio' => '12:00', 'hora_fim' => '13:00'])->assertSessionHasNoErrors();
        $this->adicionar(['hora_inicio' => '11:00', 'hora_fim' => '13:00', 'dia_semana' => 2])->assertSessionHasNoErrors();

        // Outro profissional pode ter o mesmo período no mesmo dia.
        $this->adicionar(['hora_inicio' => '09:00', 'hora_fim' => '12:00'], $this->criarProfissional('2'))->assertSessionHasNoErrors();

        $this->assertSame(4, Horario::count());
    }

    public function test_remove_periodo_sem_consultas_futuras(): void
    {
        $horario = Horario::create(['profissional_id' => $this->medica->id, 'dia_semana' => 1, 'hora_inicio' => '09:00:00', 'hora_fim' => '12:00:00']);

        $this->actingAs($this->medica->user)->delete(route('profissional.horario.destroy', $horario))
            ->assertSessionHas('success');

        $this->assertSame(0, Horario::count());
    }

    public function test_nao_remove_periodo_com_consulta_futura_mas_ignora_canceladas(): void
    {
        $horario = Horario::create(['profissional_id' => $this->medica->id, 'dia_semana' => 1, 'hora_inicio' => '09:00:00', 'hora_fim' => '12:00:00']);
        $especialidade = Especialidade::create(['nome' => 'Cardiologia']);
        $this->medica->especialidades()->attach($especialidade->id);
        $paciente = Paciente::create(['nome' => 'P', 'num_utente' => '123456789']);

        $consulta = Consulta::registarPedido([
            'paciente_id' => $paciente->id, 'profissional_id' => $this->medica->id,
            'especialidade_id' => $especialidade->id, 'inicio' => '2026-11-16 10:00:00',
        ], EstadoConsulta::Pedida, null);

        $this->actingAs($this->medica->user)->delete(route('profissional.horario.destroy', $horario))
            ->assertSessionHas('error');
        $this->assertSame(1, Horario::count());

        $consulta->mudarEstado(EstadoConsulta::Cancelada, null);

        $this->actingAs($this->medica->user)->delete(route('profissional.horario.destroy', $horario))
            ->assertSessionHas('success');
        $this->assertSame(0, Horario::count());
    }

    public function test_nao_remove_horario_de_outro_profissional(): void
    {
        $outro = $this->criarProfissional('2');
        $horario = Horario::create(['profissional_id' => $outro->id, 'dia_semana' => 1, 'hora_inicio' => '09:00:00', 'hora_fim' => '12:00:00']);

        $this->actingAs($this->medica->user)->delete(route('profissional.horario.destroy', $horario))->assertForbidden();

        $this->assertSame(1, Horario::count());
    }

    public function test_so_profissionais_acedem(): void
    {
        $this->get(route('profissional.horario.index'))->assertRedirect(route('login'));

        $paciente = User::factory()->create(['perfil_id' => Perfil::create(['nome' => 'paciente'])->id]);
        $this->actingAs($paciente)->get(route('profissional.horario.index'))->assertForbidden();

        $semFicha = User::factory()->create(['perfil_id' => $this->perfilProfissional->id]);
        $this->actingAs($semFicha)->get(route('profissional.horario.index'))
            ->assertRedirect(route('home'))->assertSessionHas('error');
    }
}
