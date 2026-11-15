<?php

namespace Tests\Feature;

use App\Enums\TipoProfissional;
use App\Models\Especialidade;
use App\Models\Perfil;
use App\Models\Profissional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfissionaisRececaoTest extends TestCase
{
    use RefreshDatabase;

    private User $rececionista;

    private Especialidade $cardiologia;

    private Especialidade $pediatria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rececionista = User::factory()->rececionista()->create();
        $this->cardiologia = Especialidade::factory()->create(['nome' => 'Cardiologia']);
        $this->pediatria = Especialidade::factory()->create(['nome' => 'Pediatria']);
    }

    /**
     * @param  array<string, mixed>  $alteracoes
     * @return array<string, mixed>
     */
    private function dados(array $alteracoes = []): array
    {
        return $alteracoes + [
            'name' => 'Marta Médica',
            'email' => 'marta@clinica.pt',
            'tipo' => 'medico',
            'num_cedula' => '54321',
            'contacto' => '912345678',
            'especialidades' => [$this->cardiologia->id, $this->pediatria->id],
            'password' => 'segredo123',
            'password_confirmation' => 'segredo123',
        ];
    }

    public function test_cria_conta_ficha_e_especialidades_de_uma_vez(): void
    {
        $this->actingAs($this->rececionista)
            ->post(route('rececao.profissionais.store'), $this->dados())
            ->assertRedirect(route('rececao.profissionais.index'))
            ->assertSessionHas('success');

        $user = User::where('email', 'marta@clinica.pt')->firstOrFail();
        $this->assertTrue($user->temPerfil(Perfil::PROFISSIONAL));
        $this->assertTrue($user->ativo);
        $this->assertTrue(Hash::check('segredo123', $user->password));

        $profissional = $user->profissional;
        $this->assertSame(TipoProfissional::Medico, $profissional->tipo);
        $this->assertSame('54321', $profissional->num_cedula);
        $this->assertSame('912345678', $profissional->contacto);
        $this->assertEqualsCanonicalizing(
            [$this->cardiologia->id, $this->pediatria->id],
            $profissional->especialidades->pluck('id')->all(),
        );
    }

    public function test_cedula_repetida_so_e_recusada_dentro_do_mesmo_tipo(): void
    {
        Profissional::factory()->medico()->create(['num_cedula' => '54321']);

        $this->actingAs($this->rececionista)
            ->post(route('rececao.profissionais.store'), $this->dados())
            ->assertSessionHasErrors('num_cedula');
        $this->assertDatabaseMissing('users', ['email' => 'marta@clinica.pt']);

        $this->actingAs($this->rececionista)
            ->post(route('rececao.profissionais.store'), $this->dados(['tipo' => 'enfermeiro']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('profissionais', ['tipo' => 'enfermeiro', 'num_cedula' => '54321']);
    }

    public function test_recusa_dados_invalidos_e_nao_cria_nada(): void
    {
        User::factory()->create(['email' => 'ocupado@clinica.pt']);
        $contas = User::count();

        $invalidos = [
            'name' => ['name' => ''],
            'email' => ['email' => 'ocupado@clinica.pt'],
            'tipo' => ['tipo' => 'dentista'],
            'num_cedula' => ['num_cedula' => ''],
            'especialidades' => ['especialidades' => []],
            'especialidades.0' => ['especialidades' => [999999]],
            'password' => ['password' => 'curta', 'password_confirmation' => 'curta'],
        ];

        foreach ($invalidos as $campo => $alteracoes) {
            $this->actingAs($this->rececionista)
                ->post(route('rececao.profissionais.store'), $this->dados($alteracoes))
                ->assertSessionHasErrors($campo);
        }

        $this->assertSame($contas, User::count());
        $this->assertSame(0, Profissional::count());
    }

    public function test_lista_e_filtra_profissionais(): void
    {
        Profissional::factory()->medico()->comEspecialidade($this->cardiologia)
            ->for(User::factory()->profissional()->state(['name' => 'Marta Médica']))
            ->create(['num_cedula' => '11111']);
        Profissional::factory()->enfermeiro()->comEspecialidade($this->pediatria)
            ->for(User::factory()->profissional()->state(['name' => 'Eduardo Enfermeiro']))
            ->create(['num_cedula' => '22222']);

        $this->actingAs($this->rececionista)->get(route('rececao.profissionais.index'))
            ->assertOk()
            ->assertSeeInOrder(['Eduardo Enfermeiro', 'Pediatria', 'Marta Médica', 'Cardiologia']);

        $this->actingAs($this->rececionista)->get(route('rececao.profissionais.index', ['tipo' => 'enfermeiro']))
            ->assertSee('Eduardo Enfermeiro')->assertDontSee('Marta Médica');

        $this->actingAs($this->rececionista)->get(route('rececao.profissionais.index', ['q' => '1111']))
            ->assertSee('Marta Médica')->assertDontSee('Eduardo Enfermeiro');

        $this->actingAs($this->rececionista)->get(route('rececao.profissionais.index', ['tipo' => 'dentista']))
            ->assertSessionHasErrors('tipo');
    }

    public function test_formulario_lista_as_especialidades_e_avisa_quando_nao_ha(): void
    {
        $this->actingAs($this->rececionista)->get(route('rececao.profissionais.create'))
            ->assertOk()
            ->assertSee('Cardiologia')->assertSee('Pediatria')
            ->assertDontSee('Ainda não há especialidades');

        Especialidade::query()->delete();

        $this->actingAs($this->rececionista)->get(route('rececao.profissionais.create'))
            ->assertOk()->assertSee('Ainda não há especialidades');
    }

    public function test_formulario_em_ingles(): void
    {
        $this->actingAs($this->rececionista)->withSession(['locale' => 'en'])
            ->get(route('rececao.profissionais.create'))
            ->assertSee('New professional')->assertSee('Licence number')->assertSee('Specialties');
    }

    public function test_so_a_rececao_gere_profissionais(): void
    {
        $this->get(route('rececao.profissionais.index'))->assertRedirect(route('login'));

        $outros = [
            User::factory()->administrador()->create(),
            User::factory()->paciente()->create(),
            Profissional::factory()->create()->user,
        ];

        foreach ($outros as $user) {
            $this->actingAs($user)->get(route('rececao.profissionais.index'))->assertForbidden();
            $this->actingAs($user)->get(route('rececao.profissionais.create'))->assertForbidden();
            $this->actingAs($user)->post(route('rececao.profissionais.store'), $this->dados())->assertForbidden();
        }

        $this->assertDatabaseMissing('users', ['email' => 'marta@clinica.pt']);
    }
}
