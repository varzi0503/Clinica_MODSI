<?php

namespace Tests\Feature;

use App\Enums\EstadoConsulta;
use App\Enums\TipoProfissional;
use App\Models\Consulta;
use App\Models\Especialidade;
use App\Models\Horario;
use App\Models\Paciente;
use App\Models\Perfil;
use App\Models\Profissional;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FactoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Quarta-feira, 18 nov. 2026, 11:00.
        $this->travelTo(CarbonImmutable::parse('2026-11-18 11:00:00'));
    }

    /** @return array<string, array{EstadoConsulta}> */
    public static function estados(): array
    {
        return collect(EstadoConsulta::cases())
            ->mapWithKeys(fn (EstadoConsulta $estado) => [$estado->value => [$estado]])
            ->all();
    }

    private function consultaNoEstado(EstadoConsulta $estado): Consulta
    {
        return match ($estado) {
            EstadoConsulta::EmEspera => Consulta::factory()->emEspera()->create(),
            EstadoConsulta::Pedida => Consulta::factory()->pedida()->create(),
            EstadoConsulta::Confirmada => Consulta::factory()->confirmada()->create(),
            EstadoConsulta::Rejeitada => Consulta::factory()->rejeitada()->create(),
            EstadoConsulta::Cancelada => Consulta::factory()->cancelada()->create(),
            EstadoConsulta::Realizada => Consulta::factory()->realizada()->create(),
            EstadoConsulta::Faltou => Consulta::factory()->faltou()->create(),
        };
    }

    #[DataProvider('estados')]
    public function test_consulta_fica_com_um_historico_que_respeita_a_matriz(EstadoConsulta $estado): void
    {
        $consulta = $this->consultaNoEstado($estado);
        $historico = $consulta->historico()->get();

        $this->assertSame($estado, $consulta->fresh()->estado);
        $this->assertSame($estado, $historico->last()->estado);
        $this->assertContains($historico->first()->estado, [EstadoConsulta::Pedida, EstadoConsulta::EmEspera]);

        foreach ($historico->sliding(2) as $par) {
            [$de, $para] = $par->values()->all();

            $this->assertTrue($de->estado->podeTransitarPara($para->estado), "{$de->estado->value} → {$para->estado->value}");
            $this->assertTrue($de->data_hora->lessThanOrEqualTo($para->data_hora));
        }
    }

    #[DataProvider('estados')]
    public function test_consulta_tem_datas_coerentes_e_nunca_no_futuro(EstadoConsulta $estado): void
    {
        $consulta = $this->consultaNoEstado($estado)->fresh();
        $historico = $consulta->historico()->get();

        $this->assertTrue($historico->first()->data_hora->equalTo($consulta->data_pedido));
        $this->assertTrue($historico->last()->data_hora->equalTo($consulta->data_estado));
        $this->assertTrue($consulta->data_pedido->lessThanOrEqualTo($consulta->data_estado));
        $this->assertTrue($consulta->data_estado->lessThanOrEqualTo(now()));
    }

    #[DataProvider('estados')]
    public function test_consulta_ocupa_a_vaga_so_nos_estados_que_a_ocupam(EstadoConsulta $estado): void
    {
        $consulta = $this->consultaNoEstado($estado);

        $vagaOcupada = DB::table('consultas')->where('id', $consulta->id)->value('vaga_ocupada');

        $this->assertSame($estado->ocupaVaga(), $vagaOcupada !== null);
    }

    public function test_realizada_e_falta_ficam_no_passado_e_sao_registadas_depois_da_hora(): void
    {
        $realizada = Consulta::factory()->realizada()->create();
        $faltou = Consulta::factory()->faltou()->create();

        foreach ([$realizada, $faltou] as $consulta) {
            $this->assertTrue($consulta->inicio->isPast());
            $this->assertTrue($consulta->data_estado->greaterThan($consulta->inicio));
        }
    }

    public function test_consulta_usa_uma_especialidade_do_profissional(): void
    {
        $consulta = Consulta::factory()->create();

        $this->assertTrue($consulta->profissional->especialidades->contains($consulta->especialidade));
    }

    public function test_consultas_do_mesmo_profissional_nao_colidem_por_omissao(): void
    {
        $profissional = Profissional::factory()->comEspecialidade()->create();

        $consultas = Consulta::factory()->count(20)->confirmada()->for($profissional)->create();

        $this->assertSame(20, $consultas->pluck('inicio')->map->toDateTimeString()->unique()->count());
        $this->assertTrue($consultas->every(fn (Consulta $c) => $c->inicio->isFuture()));
    }

    public function test_consulta_aceita_inicio_e_paciente_dados(): void
    {
        $paciente = Paciente::factory()->semConta()->create();

        $consulta = Consulta::factory()->confirmada()->for($paciente)->create(['inicio' => '2026-11-20 10:00:00']);

        $this->assertSame('2026-11-20 10:00:00', $consulta->inicio->toDateTimeString());
        $this->assertSame($paciente->id, $consulta->paciente_id);
        $this->assertTrue($consulta->data_estado->lessThanOrEqualTo(now()));
    }

    public function test_com_autores_atribui_cada_passo_do_historico_a_quem_o_faria(): void
    {
        $rececionista = User::factory()->rececionista()->create();

        $realizada = Consulta::factory()->realizada()->comAutores($rececionista)->create();
        $balcao = Consulta::factory()->rejeitada()->comAutores($rececionista)
            ->for(Paciente::factory()->semConta())->create();

        $this->assertSame(
            [$realizada->paciente->user_id, $rececionista->id, $realizada->profissional->user_id],
            $realizada->historico()->pluck('user_id')->all(),
        );
        $this->assertSame([$rececionista->id, $rececionista->id], $balcao->historico()->pluck('user_id')->all());
    }

    public function test_sem_autores_o_historico_fica_como_mudanca_do_sistema(): void
    {
        $consulta = Consulta::factory()->confirmada()->create();

        $this->assertSame([null, null], $consulta->historico()->pluck('user_id')->all());
    }

    public function test_profissional_tem_conta_com_o_perfil_profissional(): void
    {
        $medico = Profissional::factory()->create();
        $enfermeira = Profissional::factory()->enfermeiro()->create();

        $this->assertSame(TipoProfissional::Medico, $medico->tipo);
        $this->assertSame(TipoProfissional::Enfermeiro, $enfermeira->tipo);
        $this->assertTrue($medico->user->temPerfil(Perfil::PROFISSIONAL));
        $this->assertSame($medico->user->perfil_id, $enfermeira->user->perfil_id);
        $this->assertSame(1, Perfil::where('nome', Perfil::PROFISSIONAL)->count());
    }

    public function test_profissional_com_especialidades_e_horarios(): void
    {
        $cardiologia = Especialidade::factory()->create(['nome' => 'Cardiologia']);

        $profissional = Profissional::factory()
            ->comEspecialidade($cardiologia)
            ->comEspecialidade()
            ->comHorario(1, '09:00', '13:00')
            ->comHorario(3, '14:00', '18:00')
            ->create();

        $this->assertCount(2, $profissional->especialidades);
        $this->assertTrue($profissional->especialidades->contains($cardiologia));
        $this->assertSame([1, 3], $profissional->horarios()->orderBy('dia_semana')->pluck('dia_semana')->all());
    }

    public function test_paciente_com_conta_tem_o_nome_da_conta_e_o_perfil_paciente(): void
    {
        $paciente = Paciente::factory()->create();

        $this->assertSame($paciente->user->name, $paciente->nome);
        $this->assertTrue($paciente->user->temPerfil(Perfil::PACIENTE));
        $this->assertMatchesRegularExpression('/^\d{9}$/', $paciente->num_utente);
    }

    public function test_paciente_sem_conta_e_uma_ficha_de_balcao(): void
    {
        $paciente = Paciente::factory()->semConta()->create();

        $this->assertNull($paciente->user_id);
        $this->assertNotEmpty($paciente->nome);
        $this->assertSame(0, User::count());
    }

    public function test_especialidades_tem_nomes_unicos_mesmo_depois_de_esgotar_a_lista(): void
    {
        $especialidades = Especialidade::factory()->count(30)->create();

        $this->assertSame(30, $especialidades->pluck('nome')->unique()->count());
    }

    public function test_especialidades_geradas_nao_colidem_com_as_criadas_a_mao(): void
    {
        // O caso dos seeders: nomes escolhidos à mão e, a seguir, factories que criam as suas.
        foreach (['Cardiologia', 'Dermatologia', 'Pediatria', 'Ortopedia'] as $nome) {
            Especialidade::factory()->create(['nome' => $nome]);
        }

        Especialidade::factory()->count(20)->create();

        $this->assertSame(24, Especialidade::count());
    }

    public function test_horario_por_omissao_e_um_periodo_valido(): void
    {
        $horario = Horario::factory()->create();

        $this->assertSame(1, $horario->dia_semana);
        $this->assertSame('09:00', substr($horario->hora_inicio, 0, 5));
        $this->assertSame('13:00', substr($horario->hora_fim, 0, 5));
        $this->assertNotNull($horario->profissional->user);
    }

    public function test_estados_de_perfil_reutilizam_o_perfil_existente(): void
    {
        $perfil = Perfil::create(['nome' => Perfil::RECECIONISTA]);

        $rececionista = User::factory()->rececionista()->create();
        $admin = User::factory()->administrador()->create();

        $this->assertSame($perfil->id, $rececionista->perfil_id);
        $this->assertTrue($admin->temPerfil(Perfil::ADMINISTRADOR));
        $this->assertSame(2, Perfil::count());
    }
}
