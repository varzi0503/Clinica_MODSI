<?php

namespace Tests\Feature;

use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DadosPessoaisTest extends TestCase
{
    use RefreshDatabase;

    public function test_paciente_edita_o_nome_e_os_contactos_na_ficha_e_na_conta(): void
    {
        $paciente = Paciente::factory()->create(['num_utente' => '123456789']);
        $email = $paciente->user->email;

        $this->actingAs($paciente->user)->get(route('dados-pessoais.edit'))
            ->assertOk()->assertSee($paciente->nome)->assertSee('123456789')->assertSee($email);

        $this->actingAs($paciente->user)
            ->put(route('dados-pessoais.update'), [
                'nome' => 'Nome Novo', 'telefone' => '911222333', 'email_contacto' => 'contacto@exemplo.pt',
                // Campos que não se mudam aqui: têm de ser ignorados.
                'num_utente' => '999999999', 'email' => 'outro@exemplo.pt', 'user_id' => 999,
            ])
            ->assertRedirect(route('dados-pessoais.edit'))
            ->assertSessionHas('success');

        $paciente->refresh();
        $this->assertSame('Nome Novo', $paciente->nome);
        $this->assertSame('Nome Novo', $paciente->user->name);
        $this->assertSame('911222333', $paciente->telefone);
        $this->assertSame('contacto@exemplo.pt', $paciente->email_contacto);
        $this->assertSame('123456789', $paciente->num_utente);
        $this->assertSame($email, $paciente->user->email);
    }

    public function test_recusa_nome_vazio_e_email_de_contacto_invalido(): void
    {
        $paciente = Paciente::factory()->create();
        $nome = $paciente->nome;

        $this->actingAs($paciente->user)->put(route('dados-pessoais.update'), ['nome' => ''])->assertSessionHasErrors('nome');
        $this->actingAs($paciente->user)->put(route('dados-pessoais.update'), ['nome' => 'Ok', 'email_contacto' => 'não é email'])->assertSessionHasErrors('email_contacto');

        $this->assertSame($nome, $paciente->fresh()->nome);
        $this->assertSame($nome, $paciente->user->fresh()->name);
    }

    public function test_cada_conta_so_altera_a_sua_propria_ficha(): void
    {
        $paciente = Paciente::factory()->create();
        $outro = Paciente::factory()->create();
        $nomeDoOutro = $outro->nome;

        $this->actingAs($paciente->user)->put(route('dados-pessoais.update'), ['nome' => 'Só Eu', 'paciente_id' => $outro->id]);

        $this->assertSame('Só Eu', $paciente->fresh()->nome);
        $this->assertSame($nomeDoOutro, $outro->fresh()->nome);
    }

    public function test_quem_nao_tem_ficha_de_paciente_nao_edita_nome_nem_contactos(): void
    {
        $contas = [
            User::factory()->rececionista()->create(),
            User::factory()->administrador()->create(),
            Profissional::factory()->create()->user,
            User::factory()->paciente()->create(),
        ];

        foreach ($contas as $user) {
            $nome = $user->name;

            $this->actingAs($user)->get(route('dados-pessoais.edit'))->assertOk()->assertSee($user->email)->assertDontSee('Nome e contactos');
            $this->actingAs($user)->put(route('dados-pessoais.update'), ['nome' => 'Intruso'])->assertForbidden();

            $this->assertSame($nome, $user->fresh()->name);
        }
    }

    public function test_qualquer_conta_altera_a_palavra_passe_indicando_a_atual(): void
    {
        foreach ([User::factory()->rececionista()->create(), Profissional::factory()->create()->user, Paciente::factory()->create()->user] as $user) {
            $this->actingAs($user)
                ->put(route('dados-pessoais.palavra-passe'), [
                    'current_password' => 'password', 'password' => 'segredo-novo-1', 'password_confirmation' => 'segredo-novo-1',
                ])
                ->assertRedirect(route('dados-pessoais.edit'))
                ->assertSessionHas('success');

            $this->assertTrue(Hash::check('segredo-novo-1', $user->fresh()->password));
        }
    }

    public function test_palavra_passe_atual_errada_curta_igual_ou_sem_confirmacao_e_recusada(): void
    {
        $user = User::factory()->rececionista()->create();

        $tentativas = [
            'current_password' => ['current_password' => 'errada', 'password' => 'segredo-novo-1', 'password_confirmation' => 'segredo-novo-1'],
            'password' => ['current_password' => 'password', 'password' => 'curta', 'password_confirmation' => 'curta'],
            'password ' => ['current_password' => 'password', 'password' => 'segredo-novo-1', 'password_confirmation' => 'outra-coisa-1'],
            'password  ' => ['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password'],
        ];

        foreach ($tentativas as $campo => $dados) {
            $this->actingAs($user)->put(route('dados-pessoais.palavra-passe'), $dados)
                ->assertSessionHasErrorsIn('palavraPasse', trim($campo));
        }

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_nova_palavra_passe_serve_para_entrar(): void
    {
        $user = User::factory()->rececionista()->create(['email' => 'rita@clinica.pt']);

        $this->actingAs($user)->put(route('dados-pessoais.palavra-passe'), [
            'current_password' => 'password', 'password' => 'segredo-novo-1', 'password_confirmation' => 'segredo-novo-1',
        ]);
        $this->post(route('logout'));

        $this->post(route('login.store'), ['email' => 'rita@clinica.pt', 'password' => 'password'])->assertSessionHasErrors();
        $this->assertGuest();

        $this->post(route('login.store'), ['email' => 'rita@clinica.pt', 'password' => 'segredo-novo-1']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_conta_desativada_a_meio_da_sessao_e_expulsa(): void
    {
        $user = User::factory()->rececionista()->create();
        $user->forceFill(['ativo' => false])->save();

        $this->actingAs($user)->put(route('dados-pessoais.palavra-passe'), [
            'current_password' => 'password', 'password' => 'segredo-novo-1', 'password_confirmation' => 'segredo-novo-1',
        ])->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_visitante_e_enviado_para_o_login(): void
    {
        $this->get(route('dados-pessoais.edit'))->assertRedirect(route('login'));
        $this->put(route('dados-pessoais.update'), ['nome' => 'x'])->assertRedirect(route('login'));
        $this->put(route('dados-pessoais.palavra-passe'), [])->assertRedirect(route('login'));
    }

    public function test_pagina_em_ingles(): void
    {
        $this->actingAs(Paciente::factory()->create()->user)->withSession(['locale' => 'en'])
            ->get(route('dados-pessoais.edit'))
            ->assertSee('Personal Details')->assertSee('Change password')->assertSee('Name and contacts');
    }
}
