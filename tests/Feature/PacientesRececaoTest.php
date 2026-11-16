<?php

namespace Tests\Feature;

use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PacientesRececaoTest extends TestCase
{
    use RefreshDatabase;

    private User $rececionista;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rececionista = User::factory()->rececionista()->create();
    }

    public function test_lista_e_pesquisa_por_nome_ou_numero_de_utente(): void
    {
        Paciente::factory()->semConta()->create(['nome' => 'Augusta Teste', 'num_utente' => '111222333']);
        $comConta = Paciente::factory()->create(['num_utente' => '999888777']);

        $this->actingAs($this->rececionista)->get(route('rececao.pacientes.index'))
            ->assertOk()->assertSee('Augusta Teste')->assertSee('Sem conta')->assertSee($comConta->user->email);

        $this->actingAs($this->rececionista)->get(route('rececao.pacientes.index', ['q' => 'august']))
            ->assertSee('Augusta Teste')->assertDontSee('999888777');

        $this->actingAs($this->rececionista)->get(route('rececao.pacientes.index', ['q' => '9998']))
            ->assertSee('999888777')->assertDontSee('Augusta Teste');
    }

    public function test_pesquisa_em_json_devolve_so_o_que_identifica_a_ficha_e_limita_os_resultados(): void
    {
        Paciente::factory()->semConta()->create(['nome' => 'Augusta Teste', 'num_utente' => '111222333', 'telefone' => '912345678']);
        Paciente::factory()->semConta()->count(15)->sequence(fn ($s) => ['nome' => 'Zulmira '.$s->index])->create();

        $this->actingAs($this->rececionista)->getJson(route('rececao.pacientes.pesquisa', ['q' => 'augusta']))
            ->assertOk()
            ->assertJsonCount(1, 'pacientes')
            ->assertJsonPath('pacientes.0.nome', 'Augusta Teste')
            ->assertJsonPath('pacientes.0.num_utente', '111222333')
            ->assertJsonMissingPath('pacientes.0.telefone');

        $this->actingAs($this->rececionista)->getJson(route('rececao.pacientes.pesquisa', ['q' => 'Zulmira']))
            ->assertJsonCount(10, 'pacientes');

        $this->actingAs($this->rececionista)->getJson(route('rececao.pacientes.pesquisa', ['q' => 'a']))->assertStatus(422);
    }

    public function test_cria_ficha_de_balcao_sem_conta(): void
    {
        $this->actingAs($this->rececionista)
            ->post(route('rececao.pacientes.store'), [
                'nome' => 'Bento Balcão', 'num_utente' => '123456789', 'telefone' => '220000000', 'email_contacto' => 'bento@exemplo.pt',
            ])
            ->assertRedirect(route('rececao.pacientes.index'))
            ->assertSessionHas('success');

        $ficha = Paciente::where('num_utente', '123456789')->sole();
        $this->assertNull($ficha->user_id);
        $this->assertSame('Bento Balcão', $ficha->nome);
        $this->assertSame('bento@exemplo.pt', $ficha->email_contacto);
    }

    public function test_recusa_dados_invalidos_e_numero_de_utente_repetido(): void
    {
        Paciente::factory()->semConta()->create(['num_utente' => '123456789']);

        $invalidos = [
            'nome' => ['nome' => ''],
            'num_utente' => ['num_utente' => '123456789'],
            'email_contacto' => ['email_contacto' => 'não é email'],
        ];

        foreach ($invalidos + ['num_utente ' => ['num_utente' => '12345']] as $campo => $alteracoes) {
            $this->actingAs($this->rececionista)
                ->post(route('rececao.pacientes.store'), $alteracoes + ['nome' => 'Bento', 'num_utente' => '987654321'])
                ->assertSessionHasErrors(trim($campo));
        }

        $this->assertSame(1, Paciente::count());
    }

    public function test_editar_muda_o_nome_na_ficha_e_na_conta(): void
    {
        $paciente = Paciente::factory()->create(['num_utente' => '123456789']);

        $this->actingAs($this->rececionista)->get(route('rececao.pacientes.edit', $paciente))
            ->assertOk()->assertSee($paciente->nome)->assertSee($paciente->user->email);

        $this->actingAs($this->rececionista)
            ->put(route('rececao.pacientes.update', $paciente), ['nome' => 'Nome Corrigido', 'num_utente' => '123456789', 'telefone' => '911111111'])
            ->assertRedirect(route('rececao.pacientes.index'))
            ->assertSessionHasNoErrors();

        $paciente->refresh();
        $this->assertSame('Nome Corrigido', $paciente->nome);
        $this->assertSame('Nome Corrigido', $paciente->user->name);
        $this->assertSame('911111111', $paciente->telefone);
    }

    public function test_editar_ficha_sem_conta_e_recusar_numero_de_utente_de_outra_ficha(): void
    {
        $ficha = Paciente::factory()->semConta()->create(['num_utente' => '111111111']);
        Paciente::factory()->semConta()->create(['num_utente' => '222222222']);

        $this->actingAs($this->rececionista)
            ->put(route('rececao.pacientes.update', $ficha), ['nome' => 'Outro Nome', 'num_utente' => '222222222'])
            ->assertSessionHasErrors('num_utente');
        $this->assertSame('111111111', $ficha->fresh()->num_utente);

        $this->actingAs($this->rececionista)
            ->put(route('rececao.pacientes.update', $ficha), ['nome' => 'Outro Nome', 'num_utente' => '111111111'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Outro Nome', $ficha->fresh()->nome);
    }

    public function test_associa_uma_conta_registada_a_uma_ficha_de_balcao(): void
    {
        $ficha = Paciente::factory()->semConta()->create(['nome' => 'Maria da Ficha']);
        $conta = User::factory()->paciente()->create(['email' => 'maria@teste.pt', 'name' => 'maria']);

        $this->actingAs($this->rececionista)
            ->post(route('rececao.pacientes.conta', $ficha), ['email' => 'maria@teste.pt'])
            ->assertRedirect(route('rececao.pacientes.edit', $ficha))
            ->assertSessionHas('success');

        $this->assertSame($conta->id, $ficha->fresh()->user_id);
        $this->assertSame('Maria da Ficha', $conta->fresh()->name);

        // A conta passa a ver as suas consultas.
        $this->actingAs($conta->fresh())->get(route('paciente.consultas.index'))->assertOk();
    }

    public function test_so_se_associam_contas_de_paciente_sem_ficha_a_fichas_sem_conta(): void
    {
        $ficha = Paciente::factory()->semConta()->create();
        $comFicha = Paciente::factory()->create();
        $profissional = Profissional::factory()->create();

        $recusados = ['ninguem@teste.pt', $profissional->user->email, $this->rececionista->email, $comFicha->user->email, 'não é email'];

        foreach ($recusados as $email) {
            $this->actingAs($this->rececionista)
                ->post(route('rececao.pacientes.conta', $ficha), ['email' => $email])
                ->assertSessionHasErrors('email');
        }
        $this->assertNull($ficha->fresh()->user_id);

        // Uma ficha que já tem conta não recebe outra.
        $outra = User::factory()->paciente()->create();
        $this->actingAs($this->rececionista)
            ->post(route('rececao.pacientes.conta', $comFicha), ['email' => $outra->email])
            ->assertSessionHas('error');
        $this->assertNotSame($outra->id, $comFicha->fresh()->user_id);
    }

    public function test_registo_com_ficha_existente_seguido_de_associacao_pela_rececao(): void
    {
        $ficha = Paciente::factory()->semConta()->create(['num_utente' => '123456789']);

        $this->post(route('registo.store'), [
            'nome' => 'Maria Silva', 'email' => 'maria@teste.pt', 'num_utente' => '123456789',
            'password' => 'segredo123', 'password_confirmation' => 'segredo123',
        ])->assertSessionHas('warning');

        // Sem ficha, a conta é encaminhada para a receção.
        $this->get(route('paciente.consultas.index'))->assertRedirect(route('home'))->assertSessionHas('error');
        $this->post(route('logout'));

        $this->actingAs($this->rececionista)
            ->post(route('rececao.pacientes.conta', $ficha), ['email' => 'maria@teste.pt'])
            ->assertSessionHas('success');

        $this->assertSame('maria@teste.pt', $ficha->fresh()->user->email);
    }

    public function test_paginas_em_ingles(): void
    {
        $ficha = Paciente::factory()->semConta()->create();

        $this->actingAs($this->rececionista)->withSession(['locale' => 'en'])
            ->get(route('rececao.pacientes.edit', $ficha))
            ->assertSee('Edit record')->assertSee('Link account')->assertSee('Patient number');
    }

    public function test_so_a_rececao_gere_pacientes(): void
    {
        $ficha = Paciente::factory()->semConta()->create(['nome' => 'Augusta Teste']);

        $this->get(route('rececao.pacientes.index'))->assertRedirect(route('login'));

        $outros = [User::factory()->administrador()->create(), User::factory()->paciente()->create(), Profissional::factory()->create()->user];

        foreach ($outros as $user) {
            $this->actingAs($user)->get(route('rececao.pacientes.index'))->assertForbidden();
            $this->actingAs($user)->getJson(route('rececao.pacientes.pesquisa', ['q' => 'Augusta']))->assertForbidden();
            $this->actingAs($user)->get(route('rececao.pacientes.create'))->assertForbidden();
            $this->actingAs($user)->post(route('rececao.pacientes.store'), ['nome' => 'Intruso', 'num_utente' => '987654321'])->assertForbidden();
            $this->actingAs($user)->get(route('rececao.pacientes.edit', $ficha))->assertForbidden();
            $this->actingAs($user)->put(route('rececao.pacientes.update', $ficha), ['nome' => 'Intruso', 'num_utente' => '987654321'])->assertForbidden();
            $this->actingAs($user)->post(route('rececao.pacientes.conta', $ficha), ['email' => $user->email])->assertForbidden();
        }

        $this->assertSame('Augusta Teste', $ficha->fresh()->nome);
        $this->assertNull($ficha->fresh()->user_id);
        $this->assertSame(1, Paciente::count());
    }
}
