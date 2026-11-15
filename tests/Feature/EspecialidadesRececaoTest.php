<?php

namespace Tests\Feature;

use App\Models\Especialidade;
use App\Models\Profissional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EspecialidadesRececaoTest extends TestCase
{
    use RefreshDatabase;

    private User $rececionista;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rececionista = User::factory()->rececionista()->create();
    }

    public function test_lista_as_especialidades_com_o_numero_de_profissionais(): void
    {
        $cardiologia = Especialidade::factory()->create(['nome' => 'Cardiologia', 'descricao' => 'Coração e vasos']);
        Especialidade::factory()->create(['nome' => 'Pediatria']);
        Profissional::factory()->count(2)->comEspecialidade($cardiologia)->create();

        $this->actingAs($this->rececionista)->get(route('rececao.especialidades.index'))
            ->assertOk()
            ->assertSeeInOrder(['Cardiologia', 'Coração e vasos', 'Pediatria'])
            ->assertViewHas('especialidades', fn ($lista) => $lista->firstWhere('nome', 'Cardiologia')->profissionais_count === 2
                && $lista->firstWhere('nome', 'Pediatria')->profissionais_count === 0);
    }

    public function test_cria_especialidade(): void
    {
        $this->actingAs($this->rececionista)
            ->post(route('rececao.especialidades.store'), ['nome' => '  Dermatologia ', 'descricao' => 'Pele'])
            ->assertRedirect(route('rececao.especialidades.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('especialidades', ['nome' => 'Dermatologia', 'descricao' => 'Pele']);
    }

    public function test_recusa_nome_vazio_longo_ou_repetido(): void
    {
        Especialidade::factory()->create(['nome' => 'Cardiologia']);

        foreach (['', str_repeat('a', 101), 'Cardiologia', ' Cardiologia '] as $nome) {
            $this->actingAs($this->rececionista)
                ->post(route('rececao.especialidades.store'), ['nome' => $nome])
                ->assertSessionHasErrors('nome');
        }

        $this->assertSame(1, Especialidade::count());
    }

    public function test_edita_especialidade_e_aceita_manter_o_proprio_nome(): void
    {
        $especialidade = Especialidade::factory()->create(['nome' => 'Cardiologia']);
        Especialidade::factory()->create(['nome' => 'Pediatria']);

        $this->actingAs($this->rececionista)->get(route('rececao.especialidades.edit', $especialidade))
            ->assertOk()->assertSee('Cardiologia');

        $this->actingAs($this->rececionista)
            ->put(route('rececao.especialidades.update', $especialidade), ['nome' => 'Cardiologia', 'descricao' => 'Nova descrição'])
            ->assertRedirect(route('rececao.especialidades.index'))
            ->assertSessionHasNoErrors();
        $this->assertSame('Nova descrição', $especialidade->fresh()->descricao);

        $this->actingAs($this->rececionista)
            ->put(route('rececao.especialidades.update', $especialidade), ['nome' => 'Pediatria'])
            ->assertSessionHasErrors('nome');
        $this->assertSame('Cardiologia', $especialidade->fresh()->nome);
    }

    public function test_remove_especialidade_sem_profissionais(): void
    {
        $especialidade = Especialidade::factory()->create();

        $this->actingAs($this->rececionista)
            ->delete(route('rececao.especialidades.destroy', $especialidade))
            ->assertRedirect(route('rececao.especialidades.index'))
            ->assertSessionHas('success');

        $this->assertModelMissing($especialidade);
    }

    public function test_nao_remove_especialidade_com_profissionais_associados(): void
    {
        $especialidade = Especialidade::factory()->create();
        Profissional::factory()->comEspecialidade($especialidade)->create();

        $this->actingAs($this->rececionista)
            ->from(route('rececao.especialidades.index'))
            ->delete(route('rececao.especialidades.destroy', $especialidade))
            ->assertRedirect(route('rececao.especialidades.index'))
            ->assertSessionHas('error');

        $this->assertModelExists($especialidade);
    }

    public function test_paginas_em_ingles(): void
    {
        Especialidade::factory()->create();

        $this->actingAs($this->rececionista)->withSession(['locale' => 'en'])
            ->get(route('rececao.especialidades.index'))
            ->assertSee('Specialties')->assertSee('New specialty')->assertSee('Remove');
    }

    public function test_so_a_rececao_gere_especialidades(): void
    {
        $especialidade = Especialidade::factory()->create(['nome' => 'Cardiologia']);

        $this->get(route('rececao.especialidades.index'))->assertRedirect(route('login'));

        $outros = [
            User::factory()->administrador()->create(),
            User::factory()->paciente()->create(),
            Profissional::factory()->create()->user,
        ];

        foreach ($outros as $user) {
            $this->actingAs($user)->get(route('rececao.especialidades.index'))->assertForbidden();
            $this->actingAs($user)->get(route('rececao.especialidades.create'))->assertForbidden();
            $this->actingAs($user)->post(route('rececao.especialidades.store'), ['nome' => 'Intrusa'])->assertForbidden();
            $this->actingAs($user)->get(route('rececao.especialidades.edit', $especialidade))->assertForbidden();
            $this->actingAs($user)->put(route('rececao.especialidades.update', $especialidade), ['nome' => 'Intrusa'])->assertForbidden();
            $this->actingAs($user)->delete(route('rececao.especialidades.destroy', $especialidade))->assertForbidden();
        }

        $this->assertSame(['Cardiologia'], Especialidade::pluck('nome')->all());
    }
}
