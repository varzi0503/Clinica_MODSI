<?php

namespace Tests\Feature;

use App\Models\Perfil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AutenticacaoTest extends TestCase
{
    use RefreshDatabase;

    private Perfil $rececionista;

    private Perfil $profissional;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rececionista = Perfil::create(['nome' => 'rececionista']);
        $this->profissional = Perfil::create(['nome' => 'profissional']);

        // Rota descartável só para testar o middleware de perfil,
        // enquanto não existem páginas reais por perfil.
        Route::middleware(['web', 'auth', 'perfil:rececionista'])
            ->get('/_teste-rececao', fn () => response('ok'));
    }

    private function user(array $atributos = []): User
    {
        return User::factory()->create(['perfil_id' => $this->rececionista->id] + $atributos);
    }

    private function entrar(User $user, string $password = 'password')
    {
        return $this->post(route('login.store'), ['email' => $user->email, 'password' => $password]);
    }

    public function test_login_valido_autentica_e_redireciona(): void
    {
        $user = $this->user();

        $this->entrar($user)->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_password_errada_nao_autentica_e_conta_a_tentativa(): void
    {
        $user = $this->user();

        $this->entrar($user, 'errada')->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(1, $user->fresh()->tentativas_falhadas);
    }

    public function test_quinta_falha_bloqueia_a_conta(): void
    {
        $user = $this->user();

        foreach (range(1, User::MAX_TENTATIVAS) as $i) {
            $this->entrar($user, 'errada');
        }

        $this->assertNotNull($user->fresh()->bloqueado_ate);

        // Mesmo com a password certa, a conta bloqueada não entra.
        $this->entrar($user)->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_bloqueio_expirado_deixa_entrar_e_limpa_o_estado(): void
    {
        $user = $this->user(['bloqueado_ate' => now()->subMinute()]);

        $this->entrar($user);

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->bloqueado_ate);
    }

    public function test_conta_desativada_nao_entra_mesmo_com_password_certa(): void
    {
        $user = $this->user(['ativo' => false]);

        $this->entrar($user)->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_termina_a_sessao(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_utilizador_autenticado_nao_ve_o_formulario_de_login(): void
    {
        $this->actingAs($this->user())->get(route('login'))->assertRedirect(route('home'));
    }

    public function test_rota_de_perfil_exige_o_perfil_certo(): void
    {
        $this->get('/_teste-rececao')->assertRedirect(route('login'));

        $outro = User::factory()->create(['perfil_id' => $this->profissional->id]);
        $this->actingAs($outro)->get('/_teste-rececao')->assertForbidden();

        $this->actingAs($this->user())->get('/_teste-rececao')->assertOk();
    }

    public function test_conta_desativada_a_meio_da_sessao_e_expulsa(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $user->forceFill(['ativo' => false])->save();

        $this->get('/_teste-rececao')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
