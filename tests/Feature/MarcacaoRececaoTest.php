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

class MarcacaoRececaoTest extends TestCase
{
    use RefreshDatabase;

    private User $rececionista;

    private Profissional $medica;

    private Profissional $enfermeiro;

    private Especialidade $cardiologia;

    private Especialidade $enfermagem;

    private Paciente $paciente;

    protected function setUp(): void
    {
        parent::setUp();

        // Segunda-feira, 16 nov. 2026, 08:00.
        $this->travelTo(CarbonImmutable::parse('2026-11-16 08:00:00'));

        $perfilRececionista = Perfil::create(['nome' => 'rececionista']);
        $perfilProfissional = Perfil::create(['nome' => 'profissional']);
        $this->rececionista = User::factory()->create(['perfil_id' => $perfilRececionista->id]);

        $this->cardiologia = Especialidade::create(['nome' => 'Cardiologia']);
        $this->enfermagem = Especialidade::create(['nome' => 'Enfermagem']);

        $this->medica = Profissional::create([
            'user_id' => User::factory()->create(['perfil_id' => $perfilProfissional->id, 'name' => 'Dra. Ana'])->id,
            'tipo' => 'medico', 'num_cedula' => '1',
        ]);
        $this->medica->especialidades()->attach($this->cardiologia->id);

        $this->enfermeiro = Profissional::create([
            'user_id' => User::factory()->create(['perfil_id' => $perfilProfissional->id])->id,
            'tipo' => 'enfermeiro', 'num_cedula' => '1',
        ]);
        $this->enfermeiro->especialidades()->attach($this->enfermagem->id);

        foreach ([$this->medica, $this->enfermeiro] as $p) {
            Horario::create(['profissional_id' => $p->id, 'dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fim' => '11:00']);
        }

        $this->paciente = Paciente::create(['nome' => 'Augusta Teste', 'num_utente' => '123456789']);
    }

    private function marcar(array $alteracoes = [])
    {
        return $this->actingAs($this->rececionista)->post(route('rececao.consultas.store'), $alteracoes + [
            'paciente_id' => $this->paciente->id,
            'especialidade_id' => $this->cardiologia->id,
            'profissional_id' => $this->medica->id,
            'inicio' => '2026-11-16T09:30',
            'motivo_consulta' => 'Dores no peito',
        ]);
    }

    public function test_marca_consulta_confirmada_com_historico_da_rececionista(): void
    {
        $this->marcar()
            ->assertRedirect(route('rececao.consultas.create'))
            ->assertSessionHas('success');

        $consulta = Consulta::sole();
        $this->assertSame(EstadoConsulta::Confirmada, $consulta->estado);
        $this->assertSame('2026-11-16 09:30:00', $consulta->inicio->format('Y-m-d H:i:s'));
        $this->assertSame('Dores no peito', $consulta->motivo_consulta);

        $historico = $consulta->historico()->get();
        $this->assertSame([EstadoConsulta::Pedida, EstadoConsulta::Confirmada], $historico->pluck('estado')->all());
        $this->assertSame([$this->rececionista->id, $this->rececionista->id], $historico->pluck('user_id')->all());
    }

    public function test_recusa_vaga_fora_do_horario_ja_passada_ou_desalinhada(): void
    {
        $this->marcar(['inicio' => '2026-11-16T12:00'])->assertSessionHasErrors('inicio');   // fora do horário
        $this->marcar(['inicio' => '2026-11-17T09:30'])->assertSessionHasErrors('inicio');   // terça: sem horário
        $this->marcar(['inicio' => '2026-11-16T09:15'])->assertSessionHasErrors('inicio');   // não alinhada a 30 min

        $this->travelTo(CarbonImmutable::parse('2026-11-16 10:00:00'));
        $this->marcar(['inicio' => '2026-11-16T09:30'])->assertSessionHasErrors('inicio');   // já passou

        $this->assertSame(0, Consulta::count());
    }

    public function test_recusa_vaga_ja_ocupada(): void
    {
        $this->marcar()->assertSessionHasNoErrors();

        $outro = Paciente::create(['nome' => 'Outro', 'num_utente' => '987654321']);
        $this->marcar(['paciente_id' => $outro->id])->assertSessionHasErrors('inicio');

        $this->assertSame(1, Consulta::count());
    }

    public function test_recusa_especialidade_que_o_profissional_nao_exerce(): void
    {
        $this->marcar(['especialidade_id' => $this->enfermagem->id])->assertSessionHasErrors('especialidade_id');

        $this->assertSame(0, Consulta::count());
    }

    public function test_paciente_nao_fica_com_duas_consultas_a_mesma_hora(): void
    {
        $this->marcar()->assertSessionHasNoErrors();

        // Outra vaga livre, mas com outro profissional à mesma hora: a BD recusa.
        $this->marcar([
            'especialidade_id' => $this->enfermagem->id,
            'profissional_id' => $this->enfermeiro->id,
        ])->assertSessionHasErrors('inicio');

        $this->assertSame(1, Consulta::count());
    }

    public function test_so_a_rececao_marca(): void
    {
        $this->get(route('rececao.consultas.create'))->assertRedirect(route('login'));

        $this->actingAs($this->medica->user)->get(route('rececao.consultas.create'))->assertForbidden();
        $this->actingAs($this->medica->user)->post(route('rececao.consultas.store'), [])->assertForbidden();
    }

    public function test_pagina_lista_pacientes_e_especialidades(): void
    {
        $this->actingAs($this->rececionista)->get(route('rececao.consultas.create'))
            ->assertOk()
            ->assertSee('Augusta Teste')
            ->assertSee('123456789')
            ->assertSee('Cardiologia');
    }
}
