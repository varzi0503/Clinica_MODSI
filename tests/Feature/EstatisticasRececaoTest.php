<?php

namespace Tests\Feature;

use App\Models\Consulta;
use App\Models\Especialidade;
use App\Models\Profissional;
use App\Models\User;
use App\Services\Estatisticas;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstatisticasRececaoTest extends TestCase
{
    use RefreshDatabase;

    private User $rececionista;

    private Especialidade $cardiologia;

    private Especialidade $pediatria;

    private Profissional $marta;

    private Profissional $eduardo;

    protected function setUp(): void
    {
        parent::setUp();

        // Quarta-feira, 18 nov. 2026, 11:00.
        $this->travelTo(CarbonImmutable::parse('2026-11-18 11:00:00'));

        $this->rececionista = User::factory()->rececionista()->create();
        $this->cardiologia = Especialidade::factory()->create(['nome' => 'Cardiologia']);
        $this->pediatria = Especialidade::factory()->create(['nome' => 'Pediatria']);

        $this->marta = Profissional::factory()->medico()
            ->for(User::factory()->profissional()->state(['name' => 'Marta Médica']))
            ->comEspecialidade($this->cardiologia)->create();
        $this->eduardo = Profissional::factory()->enfermeiro()
            ->for(User::factory()->profissional()->state(['name' => 'Eduardo Enfermeiro']))
            ->comEspecialidade($this->pediatria)->create();

        // Marta: duas confirmadas em novembro e uma realizada em outubro. Eduardo: uma falta em outubro.
        Consulta::factory()->confirmada()->for($this->marta)->create(['inicio' => '2026-11-20 10:00:00']);
        Consulta::factory()->confirmada()->for($this->marta)->create(['inicio' => '2026-11-30 16:30:00']);
        Consulta::factory()->realizada()->for($this->marta)->create(['inicio' => '2026-10-05 09:00:00']);
        Consulta::factory()->faltou()->for($this->eduardo)->create(['inicio' => '2026-10-20 09:00:00']);
    }

    public function test_sem_periodo_conta_todas_as_consultas(): void
    {
        $this->actingAs($this->rececionista)->get(route('rececao.estatisticas.index'))
            ->assertOk()
            ->assertViewHas('total', 4)
            ->assertViewHas('estados', fn ($estados) => $estados->pluck('total', 'valor')->all() === [
                'em_espera' => 0, 'pedida' => 0, 'confirmada' => 2, 'rejeitada' => 0,
                'cancelada' => 0, 'realizada' => 1, 'faltou' => 1,
            ])
            ->assertSeeInOrder(['Consultas por estado', 'Consultas por profissional', 'Eduardo Enfermeiro', 'Marta Médica', 'Consultas por especialidade']);
    }

    public function test_totais_por_profissional_e_por_especialidade(): void
    {
        $estatisticas = app(Estatisticas::class);

        $marta = $estatisticas->porProfissional()->firstWhere('id', $this->marta->id);
        $this->assertSame('Marta Médica', $marta['nome']);
        $this->assertSame('medico', $marta['tipo']);
        $this->assertSame(3, $marta['total']);
        $this->assertSame(2, $marta['estados']['confirmada']);
        $this->assertSame(1, $marta['estados']['realizada']);
        $this->assertSame(0, $marta['estados']['faltou']);

        $pediatria = $estatisticas->porEspecialidade()->firstWhere('nome', 'Pediatria');
        $this->assertSame(1, $pediatria['total']);
        $this->assertSame(1, $pediatria['estados']['faltou']);
    }

    public function test_periodo_filtra_pela_data_da_consulta_com_os_dois_dias_incluidos(): void
    {
        $estatisticas = app(Estatisticas::class);
        $de = CarbonImmutable::parse('2026-11-20');
        $ate = CarbonImmutable::parse('2026-11-30');

        $this->assertSame(2, array_sum($estatisticas->porEstado($de, $ate)));
        $this->assertSame(2, $estatisticas->porEstado($de, $ate)['confirmada']);

        // Só "até": tudo até ao fim desse dia. Só "de": tudo a partir desse dia.
        $this->assertSame(3, array_sum($estatisticas->porEstado(null, CarbonImmutable::parse('2026-11-20'))));
        $this->assertSame(1, array_sum($estatisticas->porEstado(CarbonImmutable::parse('2026-11-21'), null)));
    }

    public function test_no_periodo_quem_nao_teve_consultas_aparece_com_zeros(): void
    {
        $linhas = app(Estatisticas::class)
            ->porProfissional(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30'))
            ->keyBy('id');

        $this->assertSame(2, $linhas[$this->marta->id]['total']);
        $this->assertSame(0, $linhas[$this->marta->id]['estados']['realizada']);
        $this->assertSame(0, $linhas[$this->eduardo->id]['total']);
        $this->assertSame(0, $linhas[$this->eduardo->id]['estados']['faltou']);

        $this->actingAs($this->rececionista)
            ->get(route('rececao.estatisticas.index', ['de' => '2026-11-01', 'ate' => '2026-11-30']))
            ->assertOk()
            ->assertViewHas('total', 2)
            ->assertSee('Eduardo Enfermeiro')
            // No gráfico só entram os que têm consultas no período.
            ->assertViewHas('graficos', fn ($graficos) => $graficos['profissionais']->pluck('nome')->all() === ['Marta Médica']);
    }

    public function test_periodo_sem_consultas_mostra_aviso_em_vez_de_graficos(): void
    {
        $this->actingAs($this->rececionista)
            ->get(route('rececao.estatisticas.index', ['de' => '2027-01-01']))
            ->assertOk()
            ->assertViewHas('total', 0)
            ->assertSee('Não há consultas neste período.')
            ->assertDontSee('data-grafico', false);
    }

    public function test_recusa_datas_invalidas_ou_trocadas(): void
    {
        $this->actingAs($this->rececionista)
            ->get(route('rececao.estatisticas.index', ['de' => 'ontem']))
            ->assertSessionHasErrors('de');

        $this->actingAs($this->rececionista)
            ->get(route('rececao.estatisticas.index', ['de' => '2026-11-30', 'ate' => '2026-11-01']))
            ->assertSessionHasErrors('ate');
    }

    public function test_pagina_em_ingles(): void
    {
        $this->actingAs($this->rececionista)->withSession(['locale' => 'en'])
            ->get(route('rececao.estatisticas.index'))
            ->assertSee('Statistics')->assertSee('Appointments by status')->assertSee('Confirmed');
    }

    public function test_so_a_rececao_ve_as_estatisticas(): void
    {
        $this->get(route('rececao.estatisticas.index'))->assertRedirect(route('login'));

        $outros = [
            User::factory()->administrador()->create(),
            User::factory()->paciente()->create(),
            $this->marta->user,
        ];

        foreach ($outros as $user) {
            $this->actingAs($user)->get(route('rececao.estatisticas.index'))->assertForbidden();
        }
    }
}
