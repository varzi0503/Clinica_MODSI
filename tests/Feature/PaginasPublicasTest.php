<?php

namespace Tests\Feature;

use App\Models\Consulta;
use App\Models\Especialidade;
use App\Models\Profissional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginasPublicasTest extends TestCase
{
    use RefreshDatabase;

    private Especialidade $cardiologia;

    private Especialidade $pediatria;

    private Profissional $ana;

    private Profissional $bruno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cardiologia = Especialidade::factory()->create(['nome' => 'Cardiologia', 'descricao' => 'Coração e vasos sanguíneos']);
        $this->pediatria = Especialidade::factory()->create(['nome' => 'Pediatria']);
        // Sem profissionais: a clínica ainda não a oferece.
        Especialidade::factory()->create(['nome' => 'Dermatologia']);

        $this->ana = Profissional::factory()->medico()
            ->for(User::factory()->profissional()->state(['name' => 'Ana Ferreira', 'email' => 'ana@clinica.pt']))
            ->comEspecialidade($this->cardiologia)->create(['num_cedula' => '55555', 'contacto' => '912000111']);
        $this->bruno = Profissional::factory()->enfermeiro()
            ->for(User::factory()->profissional()->state(['name' => 'Bruno Carvalho']))
            ->comEspecialidade($this->pediatria)->create();
    }

    public function test_pagina_inicial_mostra_contactos_horario_e_servicos_sem_sessao(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Clínica MODSI')
            ->assertSee(config('clinica.morada'))
            ->assertSee(config('clinica.telefone'))
            ->assertSee('Todos os dias, das 08:00 às 20:00.')
            ->assertSee('Cardiologia')->assertSee('Pediatria')
            ->assertDontSee('Dermatologia')
            ->assertSee(route('registo'));
    }

    public function test_especialidades_lista_so_as_que_tem_profissionais(): void
    {
        $this->get(route('especialidades.index'))
            ->assertOk()
            ->assertSeeInOrder(['Cardiologia', 'Coração e vasos sanguíneos', 'Pediatria'])
            ->assertDontSee('Dermatologia');
    }

    public function test_profissionais_mostra_nome_tipo_e_especialidades_e_nada_de_dados_pessoais(): void
    {
        $this->get(route('profissionais.index'))
            ->assertOk()
            ->assertSeeInOrder(['Ana Ferreira', 'Médico', 'Cardiologia', 'Bruno Carvalho', 'Enfermeiro', 'Pediatria'])
            ->assertDontSee('ana@clinica.pt')
            ->assertDontSee('55555')
            ->assertDontSee('912000111');
    }

    public function test_profissionais_filtra_por_especialidade_e_por_tipo(): void
    {
        $this->get(route('profissionais.index', ['especialidade_id' => $this->cardiologia->id]))
            ->assertSee('Ana Ferreira')->assertDontSee('Bruno Carvalho');

        $this->get(route('profissionais.index', ['tipo' => 'enfermeiro']))
            ->assertSee('Bruno Carvalho')->assertDontSee('Ana Ferreira');

        $this->get(route('profissionais.index', ['tipo' => 'dentista']))->assertSessionHasErrors('tipo');
        $this->get(route('profissionais.index', ['especialidade_id' => 999999]))->assertSessionHasErrors('especialidade_id');
    }

    public function test_profissional_com_a_conta_desativada_ou_sem_especialidades_nao_aparece(): void
    {
        $this->bruno->user->forceFill(['ativo' => false])->save();
        Profissional::factory()->for(User::factory()->profissional()->state(['name' => 'Sem Especialidade']))->create();

        $this->get(route('profissionais.index'))
            ->assertSee('Ana Ferreira')->assertDontSee('Bruno Carvalho')->assertDontSee('Sem Especialidade');
    }

    public function test_paginas_publicas_nao_mostram_pacientes_nem_consultas(): void
    {
        $consulta = Consulta::factory()->confirmada()->for($this->ana)->create(['motivo_consulta' => 'Motivo reservado']);

        foreach (['home', 'especialidades.index', 'profissionais.index'] as $rota) {
            $this->get(route($rota))->assertOk()
                ->assertDontSee($consulta->paciente->nome)
                ->assertDontSee($consulta->paciente->num_utente)
                ->assertDontSee('Motivo reservado');
        }
    }

    public function test_profissionais_sao_paginados(): void
    {
        Profissional::factory()->count(14)->comEspecialidade($this->cardiologia)->create();

        $this->get(route('profissionais.index'))->assertOk()
            ->assertViewHas('profissionais', fn ($lista) => $lista->count() === 12 && $lista->total() === 16)
            ->assertSee('page=2', false);

        $this->get(route('profissionais.index', ['page' => 2]))
            ->assertViewHas('profissionais', fn ($lista) => $lista->count() === 4);
    }

    public function test_paginas_em_ingles_e_menu_publico(): void
    {
        $this->withSession(['locale' => 'en'])->get(route('home'))
            ->assertSee('Opening hours')->assertSee('Every day, from 08:00 to 20:00.')
            ->assertSee(route('especialidades.index'))->assertSee(route('profissionais.index'));

        $this->withSession(['locale' => 'en'])->get(route('profissionais.index'))->assertSee('Health professionals');
    }
}
