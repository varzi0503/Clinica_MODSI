<?php

namespace Tests\Feature;

use App\Models\Paciente;
use App\Models\Perfil;
use App\Models\Profissional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUtilizadoresTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, Perfil> */
    private array $perfis;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Perfil::NOMES as $nome) {
            $this->perfis[$nome] = Perfil::create(['nome' => $nome]);
        }
        $this->admin = $this->user('administrador', ['name' => 'Ana Admin']);
    }

    private function user(string $perfil, array $atributos = []): User
    {
        return User::factory()->create(['perfil_id' => $this->perfis[$perfil]->id] + $atributos);
    }

    public function test_lista_e_filtra_utilizadores(): void
    {
        $this->user('rececionista', ['name' => 'Rita Rececao', 'email' => 'rita@clinica.pt']);
        $this->user('paciente', ['name' => 'Paulo Paciente']);

        $this->actingAs($this->admin)->get(route('admin.utilizadores.index'))
            ->assertOk()->assertSee('Rita Rececao')->assertSee('Paulo Paciente');

        $this->actingAs($this->admin)->get(route('admin.utilizadores.index', ['perfil' => 'rececionista']))
            ->assertSee('Rita Rececao')->assertDontSee('Paulo Paciente');

        $this->actingAs($this->admin)->get(route('admin.utilizadores.index', ['q' => 'rita@']))
            ->assertSee('Rita Rececao')->assertDontSee('Paulo Paciente');
    }

    public function test_cria_rececionista_com_password_com_hash(): void
    {
        $this->actingAs($this->admin)->post(route('admin.utilizadores.store'), [
            'name' => 'Rui Rececao',
            'email' => 'rui@clinica.pt',
            'perfil_id' => $this->perfis['rececionista']->id,
            'password' => 'segredo123',
            'password_confirmation' => 'segredo123',
        ])->assertRedirect(route('admin.utilizadores.index'))->assertSessionHas('success');

        $user = User::where('email', 'rui@clinica.pt')->sole();
        $this->assertSame('rececionista', $user->perfil->nome);
        $this->assertTrue($user->ativo);
        $this->assertTrue(Hash::check('segredo123', $user->password));
    }

    public function test_nao_cria_contas_de_profissional_nem_de_paciente(): void
    {
        foreach (['profissional', 'paciente'] as $perfil) {
            $this->actingAs($this->admin)->post(route('admin.utilizadores.store'), [
                'name' => 'X', 'email' => "{$perfil}@clinica.pt",
                'perfil_id' => $this->perfis[$perfil]->id,
                'password' => 'segredo123', 'password_confirmation' => 'segredo123',
            ])->assertSessionHasErrors('perfil_id');
        }

        $this->assertSame(1, User::count());
    }

    public function test_muda_perfil_entre_administrador_e_rececionista(): void
    {
        $rececionista = $this->user('rececionista');

        $this->actingAs($this->admin)
            ->patch(route('admin.utilizadores.perfil', $rececionista), ['perfil_id' => $this->perfis['administrador']->id])
            ->assertSessionHas('success');

        $this->assertSame('administrador', $rececionista->fresh()->perfil->nome);

        $this->actingAs($this->admin)
            ->patch(route('admin.utilizadores.perfil', $rececionista), ['perfil_id' => $this->perfis['paciente']->id])
            ->assertSessionHasErrors('perfil_id');
    }

    public function test_nao_muda_perfil_de_contas_com_ficha_nem_o_proprio(): void
    {
        $comPaciente = $this->user('paciente');
        Paciente::create(['user_id' => $comPaciente->id, 'nome' => 'P', 'num_utente' => '123456789']);
        $comProfissional = $this->user('profissional');
        Profissional::create(['user_id' => $comProfissional->id, 'tipo' => 'medico', 'num_cedula' => '1']);

        foreach ([$comPaciente, $comProfissional, $this->admin] as $alvo) {
            $antes = $alvo->perfil_id;
            $this->actingAs($this->admin)
                ->patch(route('admin.utilizadores.perfil', $alvo), ['perfil_id' => $this->perfis['rececionista']->id])
                ->assertSessionHas('error');
            $this->assertSame($antes, $alvo->fresh()->perfil_id);
        }
    }

    public function test_desativa_e_reativa_limpando_o_bloqueio(): void
    {
        $user = $this->user('rececionista');

        $this->actingAs($this->admin)->patch(route('admin.utilizadores.desativar', $user))->assertSessionHas('success');
        $this->assertFalse($user->fresh()->ativo);

        $user->forceFill(['tentativas_falhadas' => 3, 'bloqueado_ate' => now()->addMinutes(10)])->save();

        $this->actingAs($this->admin)->patch(route('admin.utilizadores.ativar', $user))->assertSessionHas('success');
        $user->refresh();
        $this->assertTrue($user->ativo);
        $this->assertSame(0, $user->tentativas_falhadas);
        $this->assertNull($user->bloqueado_ate);
    }

    public function test_admin_nao_se_desativa(): void
    {
        $this->actingAs($this->admin)->patch(route('admin.utilizadores.desativar', $this->admin))->assertSessionHas('error');

        $this->assertTrue($this->admin->fresh()->ativo);
    }

    public function test_so_administradores_acedem(): void
    {
        $this->get(route('admin.utilizadores.index'))->assertRedirect(route('login'));

        $rececionista = $this->user('rececionista');
        $this->actingAs($rececionista)->get(route('admin.utilizadores.index'))->assertForbidden();
        $this->actingAs($rececionista)->patch(route('admin.utilizadores.desativar', $this->admin))->assertForbidden();

        $this->assertTrue($this->admin->fresh()->ativo);
    }
}
