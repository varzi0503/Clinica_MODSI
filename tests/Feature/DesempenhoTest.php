<?php

namespace Tests\Feature;

use App\Enums\EstadoConsulta;
use App\Models\Consulta;
use App\Models\Especialidade;
use App\Models\Paciente;
use App\Models\Perfil;
use App\Models\Profissional;
use App\Models\User;
use App\Services\Disponibilidade;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Issue #80 — medição de desempenho (RNF06: operações habituais em menos de 3 segundos).
 *
 * Cria um volume de dados de uma clínica com alguma atividade (cerca de oito meses de consultas)
 * e mede as operações que o RNF06 refere. Cada operação corre uma vez para aquecer e depois cinco
 * vezes; a tabela final (mediana e máximo, em milissegundos) é impressa e gravada em
 * storage/app/desempenho.md, para o capítulo 6 do relatório.
 *
 * Mede o tempo da aplicação a responder ao pedido (controlador, base de dados e vista), sem a rede
 * nem o servidor web. Está fora da suite normal; para obter números com significado, correr contra
 * MySQL ou MariaDB:
 *   DB_CONNECTION=mysql DB_DATABASE=clinica_test php artisan test --group=desempenho
 */
#[Group('desempenho')]
class DesempenhoTest extends TestCase
{
    use RefreshDatabase;

    private const PROFISSIONAIS = 30;

    private const PACIENTES = 3000;

    private const DIAS_PASSADOS = 180;

    private const DIAS_FUTUROS = 60;

    /** Fração das vagas de cada dia que tem consulta. */
    private const OCUPACAO = 0.35;

    private const REPETICOES = 5;

    private const LIMITE_MS = 3000;

    /** @var list<array{0: string, 1: float, 2: float}> */
    private array $medicoes = [];

    public function test_operacoes_habituais_respondem_em_menos_de_tres_segundos(): void
    {
        $inicio = hrtime(true);
        [$especialidade, $profissional, $paciente, $rececionista] = $this->criarDados();
        $segundosACriar = (hrtime(true) - $inicio) / 1e9;

        $hoje = CarbonImmutable::today();

        // "Listar horários": vagas de 14 dias de uma especialidade, como o paciente as vê (com as ocupadas).
        $this->medir('Disponibilidade: vagas de 14 dias de uma especialidade', $paciente->user, fn () => $this->getJson(
            route('disponibilidade.vagas', ['especialidade_id' => $especialidade->id, 'dias' => 14])
        ));

        // "Marcar": o paciente pede consultas em vagas livres (uma vaga diferente em cada repetição).
        $vagas = app(Disponibilidade::class)->vagasLivres($profissional, $hoje->addDays(7), $hoje->addDays(13))->values();
        $this->medir('Pedir consulta (paciente)', $paciente->user, function (int $n) use ($vagas, $profissional, $especialidade) {
            return $this->post(route('paciente.pedidos.store'), [
                'especialidade_id' => $especialidade->id,
                'profissional_id' => $profissional->id,
                'inicio' => $vagas[$n]->format('Y-m-d\TH:i'),
            ])->assertSessionHasNoErrors();
        }, aquecer: false);

        // "Consultar agenda".
        $this->medir('Agenda do profissional (semana)', $profissional->user, fn () => $this->get(route('profissional.agenda.index', ['vista' => 'semana'])));
        $this->medir('Agenda geral da receção (dia)', $rececionista, fn () => $this->get(route('rececao.agenda.index')));
        $this->medir('Agenda geral da receção (semana)', $rececionista, fn () => $this->get(route('rececao.agenda.index', ['vista' => 'semana'])));

        // Outras listagens do dia a dia.
        $this->medir('Pedidos pendentes (receção)', $rececionista, fn () => $this->get(route('rececao.pedidos.index')));
        $this->medir('Consultas a partir de hoje (receção)', $rececionista, fn () => $this->get(route('rececao.consultas.index')));
        $this->medir('Pesquisa de pacientes (marcar consulta)', $rececionista, fn () => $this->getJson(route('rececao.pacientes.pesquisa', ['q' => 'Paciente 12'])));
        $this->medir('As minhas consultas, passadas (paciente)', $paciente->user, fn () => $this->get(route('paciente.consultas.index', ['vista' => 'passadas'])));
        $this->medir('Histórico de um paciente (profissional)', $profissional->user, fn () => $this->get(route('profissional.pacientes.historico', $paciente)));
        $this->medir('Estatísticas, sem período (vistas SQL)', $rececionista, fn () => $this->get(route('rececao.estatisticas.index')));
        $this->medir('Estatísticas, últimos 30 dias', $rececionista, fn () => $this->get(route('rececao.estatisticas.index', [
            'de' => $hoje->subDays(30)->toDateString(), 'ate' => $hoje->toDateString(),
        ])));

        $this->relatorio($segundosACriar);

        foreach ($this->medicoes as [$operacao, , $maximo]) {
            $this->assertLessThan(self::LIMITE_MS, $maximo, "{$operacao} demorou {$maximo} ms.");
        }
    }

    /**
     * Mede uma operação: um pedido para aquecer (opcional) e REPETICOES pedidos medidos.
     *
     * @param  callable(int): mixed  $pedido  recebe o número da repetição
     */
    private function medir(string $operacao, User $como, callable $pedido, bool $aquecer = true): void
    {
        $this->actingAs($como);

        if ($aquecer) {
            $pedido(0);
        }

        $tempos = [];
        foreach (range(0, self::REPETICOES - 1) as $n) {
            $t = hrtime(true);
            $resposta = $pedido($n);
            $tempos[] = (hrtime(true) - $t) / 1e6;

            $this->assertContains($resposta->getStatusCode(), [200, 302], "{$operacao} respondeu {$resposta->getStatusCode()}.");
        }

        sort($tempos);
        $this->medicoes[] = [$operacao, $tempos[intdiv(count($tempos), 2)], end($tempos)];
    }

    private function relatorio(float $segundosACriar): void
    {
        $linhas = [
            '# Medição de desempenho (RNF06)',
            '',
            sprintf('Data: %s · Base de dados: %s %s · PHP %s', now()->format('d/m/Y H:i'), DB::getDriverName(), DB::connection()->getServerVersion(), PHP_VERSION),
            sprintf(
                'Dados: %d profissionais, %d pacientes, %d consultas, %d registos de histórico (criados em %.0f s).',
                Profissional::count(), Paciente::count(), Consulta::count(), DB::table('historico_estados')->count(), $segundosACriar,
            ),
            sprintf('Cada operação: 1 pedido de aquecimento e %d pedidos medidos. Tempo da aplicação, sem rede.', self::REPETICOES),
            '',
            '| Operação | Mediana (ms) | Máximo (ms) |',
            '|---|---:|---:|',
        ];

        foreach ($this->medicoes as [$operacao, $mediana, $maximo]) {
            $linhas[] = sprintf('| %s | %.0f | %.0f |', $operacao, $mediana, $maximo);
        }

        $texto = implode(PHP_EOL, $linhas).PHP_EOL;
        file_put_contents(storage_path('app/desempenho.md'), $texto);
        fwrite(STDOUT, PHP_EOL.$texto);
    }

    /**
     * Especialidades, profissionais com horário de segunda a sexta (09:00–17:00), pacientes com
     * conta e consultas em todos os estados. As consultas e as contas são inseridas em bloco,
     * porque criá-las uma a uma pelas factories levaria muitos minutos.
     *
     * @return array{0: Especialidade, 1: Profissional, 2: Paciente, 3: User}
     */
    private function criarDados(): array
    {
        $rececionista = User::factory()->rececionista()->create();
        $especialidades = Especialidade::factory()->count(12)->create();

        $profissionais = collect(range(0, self::PROFISSIONAIS - 1))->map(function (int $i) use ($especialidades) {
            $fabrica = Profissional::factory()->comEspecialidade($especialidades[$i % 12]);
            foreach (range(1, 5) as $dia) {
                $fabrica = $fabrica->comHorario($dia, '09:00', '17:00');
            }

            return $fabrica->create();
        });

        $pacientes = $this->criarPacientes();
        $this->criarConsultas($profissionais->all(), $pacientes);

        // Medem-se sempre o primeiro profissional, a sua especialidade e um paciente com histórico com ele.
        $profissional = $profissionais->first();
        $paciente = Paciente::findOrFail(Consulta::where('profissional_id', $profissional->id)->value('paciente_id'));

        return [$especialidades->first(), $profissional, $paciente, $rececionista];
    }

    /** @return list<int> ids das fichas criadas */
    private function criarPacientes(): array
    {
        $perfil = Perfil::firstOrCreate(['nome' => Perfil::PACIENTE])->id;
        $palavraPasse = Hash::make('password');
        $agora = now();

        foreach (array_chunk(range(1, self::PACIENTES), 1000) as $bloco) {
            DB::table('users')->insert(array_map(fn (int $i) => [
                'perfil_id' => $perfil, 'name' => "Paciente {$i}", 'email' => "paciente{$i}@desempenho.test",
                'password' => $palavraPasse, 'ativo' => true, 'tentativas_falhadas' => 0, 'created_at' => $agora, 'updated_at' => $agora,
            ], $bloco));
        }

        $contas = DB::table('users')->where('email', 'like', '%@desempenho.test')->orderBy('id')->pluck('id')->all();

        foreach (array_chunk($contas, 1000, true) as $bloco) {
            DB::table('pacientes')->insert(array_map(fn (int $conta, int $i) => [
                'user_id' => $conta, 'nome' => 'Paciente '.($i + 1), 'num_utente' => sprintf('9%08d', $i + 1),
                'created_at' => $agora, 'updated_at' => $agora,
            ], $bloco, array_keys($bloco)));
        }

        return DB::table('pacientes')->whereIn('user_id', $contas)->orderBy('id')->pluck('id')->all();
    }

    /**
     * @param  list<Profissional>  $profissionais
     * @param  list<int>  $pacientes
     */
    private function criarConsultas(array $profissionais, array $pacientes): void
    {
        $hoje = CarbonImmutable::today();
        $agora = CarbonImmutable::now();
        $linhas = [];
        $vaga = 0;
        $especialidadeDe = array_map(fn (Profissional $p) => $p->especialidades->first()->id, $profissionais);

        for ($dia = $hoje->subDays(self::DIAS_PASSADOS); $dia->lte($hoje->addDays(self::DIAS_FUTUROS)); $dia = $dia->addDay()) {
            if ($dia->isWeekend()) {
                continue;
            }

            foreach (range(0, 15) as $meiaHora) {
                $inicio = $dia->setTime(9, 0)->addMinutes(30 * $meiaHora);
                $vaga++;

                foreach ($profissionais as $p => $profissional) {
                    // Ocupação pseudoaleatória mas repetível.
                    if (crc32("{$vaga}-{$p}") % 100 >= self::OCUPACAO * 100) {
                        continue;
                    }

                    // À mesma hora, cada profissional tem um paciente diferente (o UNIQUE por paciente não deixa repetir).
                    $paciente = $pacientes[($vaga * self::PROFISSIONAIS + $p) % count($pacientes)];
                    $sorte = crc32("estado-{$vaga}-{$p}") % 100;
                    $estado = $inicio->lessThan($agora)
                        ? ($sorte < 85 ? EstadoConsulta::Realizada : ($sorte < 92 ? EstadoConsulta::Faltou : EstadoConsulta::Cancelada))
                        : ($sorte < 70 ? EstadoConsulta::Confirmada : ($sorte < 90 ? EstadoConsulta::Pedida : EstadoConsulta::Cancelada));

                    $linhas[] = [
                        'paciente_id' => $paciente,
                        'profissional_id' => $profissional->id,
                        'especialidade_id' => $especialidadeDe[$p],
                        'inicio' => $inicio->toDateTimeString(),
                        'estado' => $estado->value,
                        'data_pedido' => $inicio->subDays(10)->toDateTimeString(),
                        'data_estado' => ($estado === EstadoConsulta::Pedida ? $inicio->subDays(10) : $inicio->subDays(5))->toDateTimeString(),
                    ];

                    // Insere aos blocos, para não guardar dezenas de milhares de linhas em memória.
                    if (count($linhas) === 1000) {
                        DB::table('consultas')->insert($linhas);
                        $linhas = [];
                    }
                }
            }
        }

        if ($linhas !== []) {
            DB::table('consultas')->insert($linhas);
        }

        // Um registo de histórico por consulta, com o estado final (chega para as páginas que o leem).
        DB::statement(
            'insert into historico_estados (consulta_id, user_id, estado, data_hora, motivo) '
            ."select id, null, estado, data_estado, case when estado = 'cancelada' then 'Motivo de exemplo' else null end from consultas"
        );
    }
}
