<?php

namespace Database\Factories;

use App\Enums\EstadoConsulta;
use App\Models\Consulta;
use App\Models\Paciente;
use App\Models\Profissional;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Consultas para testes e seeders.
 *
 * É a única exceção à regra "estados só por registarPedido() e mudarEstado()": a factory escreve
 * o estado final diretamente, porque precisa de criar consultas no passado (realizadas, faltas)
 * e o código da aplicação recusa vagas que já passaram. Para compensar, cada consulta criada
 * fica com o caminho completo em historico_estados (D23), e esse caminho respeita a matriz de
 * transições — há um teste que o verifica para todos os estados.
 *
 * A coluna vaga_ocupada é gerada pela base de dados e nunca se escreve aqui.
 *
 * @extends Factory<Consulta>
 */
class ConsultaFactory extends Factory
{
    /** Quantas vagas esta factory já atribuiu: evita duas consultas na mesma vaga por omissão. */
    private static int $vagasFuturas = 0;

    private static int $vagasPassadas = 0;

    /**
     * Por omissão: pedido pendente numa vaga futura, com paciente e profissional novos.
     * A especialidade é a primeira do profissional (a FK composta exige que ele a exerça).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'paciente_id' => Paciente::factory(),
            'profissional_id' => Profissional::factory()->comEspecialidade(),
            'especialidade_id' => fn (array $atributos): int => Profissional::findOrFail($atributos['profissional_id'])
                ->especialidades()->orderBy('especialidades.id')->firstOrFail()->id,
            'inicio' => fn (): CarbonImmutable => self::proximaVagaFutura(),
            'estado' => EstadoConsulta::Pedida,
            'data_pedido' => fn (array $atributos): CarbonImmutable => self::referencia($atributos)->subDays(2),
            'data_estado' => fn (array $atributos): CarbonImmutable => self::dataDoEstado($atributos),
            'motivo_consulta' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Consulta $consulta): void {
            $caminho = self::caminho($consulta->estado);

            foreach ($caminho as $passo => $estado) {
                $ultimo = $passo === count($caminho) - 1;

                $consulta->historico()->create([
                    'user_id' => null,
                    'estado' => $estado,
                    // O primeiro registo é o pedido, o último é o estado atual, e a confirmação fica a meio.
                    'data_hora' => match (true) {
                        $passo === 0 => $consulta->data_pedido,
                        $ultimo => $consulta->data_estado,
                        default => $consulta->data_pedido->addDay(),
                    },
                    'motivo' => $ultimo && in_array($estado, [EstadoConsulta::Rejeitada, EstadoConsulta::Cancelada], true)
                        ? 'Motivo de exemplo'
                        : null,
                ]);
            }
        });
    }

    /** Pedido para uma vaga ocupada: fica na lista de espera e não ocupa a vaga (D21). */
    public function emEspera(): static
    {
        return $this->state(['estado' => EstadoConsulta::EmEspera]);
    }

    public function pedida(): static
    {
        return $this->state(['estado' => EstadoConsulta::Pedida]);
    }

    public function confirmada(): static
    {
        return $this->state(['estado' => EstadoConsulta::Confirmada]);
    }

    public function rejeitada(): static
    {
        return $this->state(['estado' => EstadoConsulta::Rejeitada]);
    }

    public function cancelada(): static
    {
        return $this->state(['estado' => EstadoConsulta::Cancelada]);
    }

    /** Consulta que já aconteceu: por omissão fica numa vaga passada. */
    public function realizada(): static
    {
        return $this->state([
            'estado' => EstadoConsulta::Realizada,
            'inicio' => fn (): CarbonImmutable => self::proximaVagaPassada(),
        ]);
    }

    /** Consulta a que o paciente faltou: por omissão fica numa vaga passada. */
    public function faltou(): static
    {
        return $this->state([
            'estado' => EstadoConsulta::Faltou,
            'inicio' => fn (): CarbonImmutable => self::proximaVagaPassada(),
        ]);
    }

    /**
     * Estados por que a consulta passou até chegar ao atual, pelo caminho mais curto da matriz.
     *
     * @return list<EstadoConsulta>
     */
    public static function caminho(EstadoConsulta $estado): array
    {
        return match ($estado) {
            EstadoConsulta::EmEspera => [EstadoConsulta::EmEspera],
            EstadoConsulta::Pedida => [EstadoConsulta::Pedida],
            EstadoConsulta::Confirmada, EstadoConsulta::Rejeitada, EstadoConsulta::Cancelada => [EstadoConsulta::Pedida, $estado],
            EstadoConsulta::Realizada, EstadoConsulta::Faltou => [EstadoConsulta::Pedida, EstadoConsulta::Confirmada, $estado],
        };
    }

    /**
     * O momento mais tardio em que o pedido podia ter sido tratado: agora, ou a hora da consulta
     * se ela já passou. As datas do pedido e do estado calculam-se para trás a partir daqui.
     *
     * @param  array<string, mixed>  $atributos
     */
    private static function referencia(array $atributos): CarbonImmutable
    {
        $inicio = CarbonImmutable::parse($atributos['inicio']);
        $agora = CarbonImmutable::now();

        return $inicio->lessThan($agora) ? $inicio : $agora;
    }

    /** @param  array<string, mixed>  $atributos */
    private static function dataDoEstado(array $atributos): CarbonImmutable
    {
        $estado = $atributos['estado'] instanceof EstadoConsulta
            ? $atributos['estado']
            : EstadoConsulta::from($atributos['estado']);

        return match ($estado) {
            // Ainda ninguém tratou o pedido.
            EstadoConsulta::EmEspera, EstadoConsulta::Pedida => CarbonImmutable::parse($atributos['data_pedido']),
            // Tratado pela receção no dia seguinte ao pedido.
            EstadoConsulta::Confirmada, EstadoConsulta::Rejeitada, EstadoConsulta::Cancelada => self::referencia($atributos)->subDay(),
            // Registado pelo profissional no fim da consulta (D25: só depois da hora de início).
            EstadoConsulta::Realizada, EstadoConsulta::Faltou => CarbonImmutable::parse($atributos['inicio'])->addMinutes(30),
        };
    }

    /** Vagas de 30 minutos entre as 09:00 e as 17:00, a partir de amanhã. */
    private static function proximaVagaFutura(): CarbonImmutable
    {
        $n = self::$vagasFuturas++;

        return CarbonImmutable::tomorrow()->addDays(intdiv($n, 16))->setTime(9, 0)->addMinutes(30 * ($n % 16));
    }

    /** O mesmo, de ontem para trás. */
    private static function proximaVagaPassada(): CarbonImmutable
    {
        $n = self::$vagasPassadas++;

        return CarbonImmutable::yesterday()->subDays(intdiv($n, 16))->setTime(9, 0)->addMinutes(30 * ($n % 16));
    }
}
