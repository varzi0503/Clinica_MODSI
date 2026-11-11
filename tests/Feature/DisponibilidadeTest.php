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
use App\Services\Disponibilidade;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisponibilidadeTest extends TestCase
{
    use RefreshDatabase;

    private Profissional $medica;

    private Especialidade $cardiologia;

    private Paciente $paciente;

    protected function setUp(): void
    {
        parent::setUp();

        // Segunda-feira, 16 nov. 2026, 08:00.
        $this->travelTo(CarbonImmutable::parse('2026-11-16 08:00:00'));

        $perfil = Perfil::create(['nome' => 'profissional']);
        $user = User::factory()->create(['perfil_id' => $perfil->id, 'name' => 'Dra. Ana']);
        $this->medica = Profissional::create(['user_id' => $user->id, 'tipo' => 'medico', 'num_cedula' => '1']);
        $this->cardiologia = Especialidade::create(['nome' => 'Cardiologia']);
        $this->medica->especialidades()->attach($this->cardiologia->id);

        // Segundas 09:00–11:00 (4 vagas) e quartas 14:00–15:00 (2 vagas).
        Horario::create(['profissional_id' => $this->medica->id, 'dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fim' => '11:00']);
        Horario::create(['profissional_id' => $this->medica->id, 'dia_semana' => 3, 'hora_inicio' => '14:00', 'hora_fim' => '15:00']);

        $this->paciente = Paciente::create(['nome' => 'Paciente', 'num_utente' => '123456789']);
    }

    private function vagas(string $de, string $ate): array
    {
        return app(Disponibilidade::class)
            ->vagasLivres($this->medica, CarbonImmutable::parse($de), CarbonImmutable::parse($ate))
            ->map(fn ($v) => $v->format('Y-m-d H:i'))
            ->all();
    }

    private function consulta(string $inicio, EstadoConsulta $estado): void
    {
        Consulta::registarPedido([
            'paciente_id' => $this->paciente->id,
            'profissional_id' => $this->medica->id,
            'especialidade_id' => $this->cardiologia->id,
            'inicio' => $inicio,
        ], $estado, null);
    }

    public function test_gera_vagas_de_30_minutos_a_partir_do_horario_semanal(): void
    {
        $this->assertSame([
            '2026-11-16 09:00', '2026-11-16 09:30', '2026-11-16 10:00', '2026-11-16 10:30',
            '2026-11-18 14:00', '2026-11-18 14:30',
        ], $this->vagas('2026-11-16', '2026-11-22'));
    }

    public function test_vagas_ocupadas_saem_mas_pedidos_em_espera_nao_ocupam(): void
    {
        $this->consulta('2026-11-16 09:30:00', EstadoConsulta::Pedida);
        $this->consulta('2026-11-16 10:00:00', EstadoConsulta::EmEspera);

        $vagas = $this->vagas('2026-11-16', '2026-11-16');

        $this->assertNotContains('2026-11-16 09:30', $vagas);
        $this->assertContains('2026-11-16 10:00', $vagas);
    }

    public function test_cancelar_volta_a_libertar_a_vaga(): void
    {
        $this->consulta('2026-11-16 09:30:00', EstadoConsulta::Pedida);
        Consulta::first()->mudarEstado(EstadoConsulta::Cancelada, null);

        $this->assertContains('2026-11-16 09:30', $this->vagas('2026-11-16', '2026-11-16'));
    }

    public function test_vagas_que_ja_passaram_nao_aparecem(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-16 09:45:00'));

        $this->assertSame(['2026-11-16 10:00', '2026-11-16 10:30'], $this->vagas('2026-11-16', '2026-11-16'));
    }

    public function test_endpoint_devolve_vagas_por_especialidade_sem_autenticacao(): void
    {
        $this->getJson(route('disponibilidade.vagas', ['especialidade_id' => $this->cardiologia->id, 'dias' => 7]))
            ->assertOk()
            ->assertJsonPath('de', '2026-11-16')
            ->assertJsonPath('ate', '2026-11-22')
            ->assertJsonPath('profissionais.0.nome', 'Dra. Ana')
            ->assertJsonPath('profissionais.0.vagas.0', '2026-11-16T09:00')
            ->assertJsonCount(6, 'profissionais.0.vagas');
    }

    public function test_endpoint_valida_os_filtros(): void
    {
        $this->getJson(route('disponibilidade.vagas'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['especialidade_id', 'profissional_id']);

        $this->getJson(route('disponibilidade.vagas', ['especialidade_id' => $this->cardiologia->id, 'de' => '2026-11-01']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('de');

        $this->getJson(route('disponibilidade.vagas', ['especialidade_id' => $this->cardiologia->id, 'dias' => 90]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('dias');
    }

    public function test_pagina_publica_lista_so_especialidades_com_profissionais(): void
    {
        Especialidade::create(['nome' => 'Dermatologia']);

        $this->get(route('disponibilidade.index'))
            ->assertOk()
            ->assertSee('Cardiologia')
            ->assertDontSee('Dermatologia');
    }
}
