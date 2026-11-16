<?php

namespace Tests\Feature;

use App\Enums\EstadoConsulta;
use App\Exceptions\TransicaoEstadoInvalida;
use App\Models\Consulta;
use App\Models\Especialidade;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\User;
use App\Services\Marcacoes;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Throwable;

/**
 * Issue #79: pedidos simultâneos para a mesma vaga e para a mesma consulta.
 *
 * Cada pedido corre num processo PHP próprio (Concurrency, driver "process"), todos acordam no
 * mesmo instante e batem na base de dados ao mesmo tempo, como dois utilizadores reais. Por isso
 * os dados não podem estar dentro de uma transação de teste: usa-se DatabaseTruncation, e no fim
 * as tabelas são esvaziadas para não sujar os testes seguintes.
 *
 * Só corre em MySQL ou MariaDB: em SQLite em memória cada processo teria a sua própria base de dados.
 *   DB_CONNECTION=mysql DB_DATABASE=clinica_test php artisan test --filter=ConcorrenciaTest
 */
class ConcorrenciaTest extends TestCase
{
    use DatabaseTruncation;

    private const PROCESSOS = 8;

    private Profissional $medica;

    private Especialidade $especialidade;

    private CarbonImmutable $vaga;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('O teste de concorrência precisa de MySQL ou MariaDB.');
        }

        // Os processos filhos não herdam o relógio do teste: a vaga tem de ser mesmo no futuro.
        $this->vaga = CarbonImmutable::today()->addWeek()->setTime(10, 0);
        $this->especialidade = Especialidade::factory()->create();
        $fabrica = Profissional::factory()->comEspecialidade($this->especialidade);
        foreach (range(1, 7) as $dia) {
            $fabrica = $fabrica->comHorario($dia, '08:00', '20:00');
        }
        $this->medica = $fabrica->create();
    }

    protected function tearDown(): void
    {
        // Os dados foram gravados de verdade; os testes seguintes contam com tabelas vazias.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->truncateTablesForAllConnections();
        }

        parent::tearDown();
    }

    /**
     * Corre a mesma ação em vários processos que arrancam juntos e devolve o resultado de cada um:
     * "ok", ou o nome curto da exceção que a ação lançou.
     *
     * @param  callable(int): void  $acao  recebe o número do processo
     * @return list<string>
     */
    private function emSimultaneo(int $processos, callable $acao): array
    {
        // Margem para todos os processos arrancarem antes de começarem a trabalhar.
        $partida = microtime(true) + 3;

        // Um closure por processo, criado num ciclo simples: o serializador de closures não distingue
        // dois closures aninhados na mesma linha.
        $tarefas = [];
        foreach (range(0, $processos - 1) as $n) {
            $tarefas[] = function () use ($acao, $n, $partida): string {
                time_sleep_until($partida);

                try {
                    $acao($n);

                    return 'ok';
                } catch (Throwable $e) {
                    return class_basename($e);
                }
            };
        }

        return array_values(Concurrency::driver('process')->run($tarefas));
    }

    public function test_varios_pacientes_a_pedir_a_mesma_vaga_ao_mesmo_tempo_so_um_fica_com_ela(): void
    {
        $pacientes = Paciente::factory()->count(self::PROCESSOS)->create()->modelKeys();
        $medica = $this->medica->id;
        $especialidade = $this->especialidade->id;
        $vaga = $this->vaga->toDateTimeString();

        $resultados = $this->emSimultaneo(self::PROCESSOS, function (int $n) use ($pacientes, $medica, $especialidade, $vaga): void {
            $paciente = Paciente::findOrFail($pacientes[$n]);
            app(Marcacoes::class)->pedir($paciente, Profissional::findOrFail($medica), $especialidade, CarbonImmutable::parse($vaga), null, $paciente->user);
        });

        $contagem = array_count_values($resultados);
        $this->assertSame(1, $contagem['ok'] ?? 0, 'Resultados: '.json_encode($resultados));
        // Os outros são recusados com uma mensagem no formulário, nunca com um erro 500.
        $this->assertSame(self::PROCESSOS - 1, $contagem[class_basename(ValidationException::class)] ?? 0, 'Resultados: '.json_encode($resultados));
        $this->assertSame(1, Consulta::where('profissional_id', $medica)->where('inicio', $vaga)->whereNotNull('vaga_ocupada')->count());
    }

    public function test_varios_cancelamentos_da_mesma_consulta_ao_mesmo_tempo_so_um_conta(): void
    {
        $consulta = Consulta::factory()->confirmada()->for($this->medica)->create(['inicio' => $this->vaga]);
        $emEspera = Consulta::factory()->count(2)->emEspera()->for($this->medica)->create(['inicio' => $this->vaga]);
        $rececionista = User::factory()->rececionista()->create();
        $id = $consulta->id;
        $autor = $rececionista->id;

        $resultados = $this->emSimultaneo(self::PROCESSOS, function () use ($id, $autor): void {
            app(Marcacoes::class)->cancelar(Consulta::findOrFail($id), User::findOrFail($autor), 'Teste de concorrência');
        });

        $contagem = array_count_values($resultados);
        $this->assertSame(1, $contagem['ok'] ?? 0, 'Resultados: '.json_encode($resultados));
        $this->assertSame(self::PROCESSOS - 1, $contagem[class_basename(TransicaoEstadoInvalida::class)] ?? 0, 'Resultados: '.json_encode($resultados));

        // Um só registo de cancelamento no histórico, e um só pedido promovido da lista de espera.
        $this->assertSame(1, $consulta->historico()->where('estado', EstadoConsulta::Cancelada)->count());
        $this->assertSame(1, Consulta::whereKey($emEspera->modelKeys())->where('estado', EstadoConsulta::Pedida)->count());
        $this->assertSame(1, Consulta::where('profissional_id', $this->medica->id)->where('inicio', $this->vaga)->whereNotNull('vaga_ocupada')->count());
    }

    public function test_duas_rececionistas_a_confirmar_o_mesmo_pedido_ao_mesmo_tempo_so_uma_confirma(): void
    {
        $pedido = Consulta::factory()->pedida()->for($this->medica)->create(['inicio' => $this->vaga]);
        $rececionistas = User::factory()->count(self::PROCESSOS)->rececionista()->create()->modelKeys();
        $id = $pedido->id;

        $resultados = $this->emSimultaneo(self::PROCESSOS, function (int $n) use ($id, $rececionistas): void {
            app(Marcacoes::class)->confirmar(Consulta::findOrFail($id), User::findOrFail($rececionistas[$n]));
        });

        $this->assertSame(1, array_count_values($resultados)['ok'] ?? 0, 'Resultados: '.json_encode($resultados));
        $this->assertSame(1, $pedido->historico()->where('estado', EstadoConsulta::Confirmada)->count());
        // O paciente recebe um aviso, não oito.
        $this->assertSame(1, $pedido->paciente->user->notifications()->count());
    }
}
