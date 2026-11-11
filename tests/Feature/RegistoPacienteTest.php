<?php

namespace Tests\Feature;

use App\Models\Paciente;
use App\Models\Perfil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistoPacienteTest extends TestCase
{
    use RefreshDatabase;

    private function registar(array $alteracoes = [])
    {
        return $this->post(route('registo.store'), $alteracoes + [
            'nome' => 'Maria Silva',
            'email' => 'maria@teste.pt',
            'num_utente' => '123456789',
            'telefone' => '912345678',
            'password' => 'segredo123',
            'password_confirmation' => 'segredo123',
        ]);
    }

    public function test_registo_cria_conta_de_paciente_com_ficha_e_inicia_sessao(): void
    {
        $this->registar()->assertRedirect(route('home'))->assertSessionHas('success');

        $user = User::where('email', 'maria@teste.pt')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('paciente', $user->perfil->nome);
        $this->assertTrue(Hash::check('segredo123', $user->password));

        $paciente = $user->paciente;
        $this->assertSame('123456789', $paciente->num_utente);
        $this->assertSame('Maria Silva', $paciente->nome);
        $this->assertSame('912345678', $paciente->telefone);
    }

    public function test_nao_se_apropria_de_uma_ficha_de_balcao_existente(): void
    {
        $ficha = Paciente::create(['nome' => 'Ficha de balcão', 'num_utente' => '123456789']);

        $this->registar()->assertSessionHasErrors('num_utente');

        $this->assertGuest();
        $this->assertSame(0, User::count());
        $this->assertNull($ficha->fresh()->user_id);
    }

    public function test_email_repetido_e_recusado(): void
    {
        User::factory()->create(['email' => 'maria@teste.pt']);

        $this->registar()->assertSessionHasErrors('email');

        $this->assertSame(0, Paciente::count());
    }

    public function test_valida_password_e_numero_de_utente(): void
    {
        $this->registar(['password' => 'curta', 'password_confirmation' => 'curta'])->assertSessionHasErrors('password');
        $this->registar(['password_confirmation' => 'outra-coisa'])->assertSessionHasErrors('password');
        $this->registar(['num_utente' => '12345'])->assertSessionHasErrors('num_utente');
        $this->registar(['num_utente' => '12345678A'])->assertSessionHasErrors('num_utente');

        $this->assertSame(0, User::count());
    }

    public function test_utilizador_autenticado_nao_ve_o_registo(): void
    {
        $user = User::factory()->create(['perfil_id' => Perfil::create(['nome' => 'paciente'])->id]);

        $this->actingAs($user)->get(route('registo'))->assertRedirect(route('home'));
    }

    public function test_pagina_de_registo_carrega(): void
    {
        $this->get(route('registo'))->assertOk()->assertSee(__('Criar conta'));
    }
}
