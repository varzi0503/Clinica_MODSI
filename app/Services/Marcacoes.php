<?php

namespace App\Services;

use App\Enums\EstadoConsulta;
use App\Exceptions\TransicaoEstadoInvalida;
use App\Models\Consulta;
use App\Models\Horario;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Regras de negócio para marcar consultas, num só sítio (issue #66).
 *
 * Os erros de negócio saem como ValidationException, já com o campo do formulário a que dizem
 * respeito, para os controladores não precisarem de try/catch. As mudanças de estado continuam
 * a passar só por Consulta::registarPedido() e ->mudarEstado().
 */
class Marcacoes
{
    /** Antecedência mínima, em horas, para o paciente cancelar uma consulta que ocupa a vaga (D34). */
    public const HORAS_ANTECEDENCIA_PACIENTE = 24;

    public function __construct(private readonly Disponibilidade $disponibilidade) {}

    /**
     * O paciente pede uma consulta: fica "pedida" até a receção a confirmar (D24).
     * Se a vaga já estiver ocupada e o paciente aceitar, o pedido fica "em_espera" (D21).
     *
     * @throws ValidationException
     */
    public function pedir(Paciente $paciente, Profissional $profissional, int $especialidadeId, CarbonImmutable $inicio, ?string $motivo, User $por, bool $aceitaEspera = false): Consulta
    {
        $this->validarEspecialidade($profissional, $especialidadeId);

        if (! $this->disponibilidade->estaLivre($profissional, $inicio)) {
            return $this->entrarEmEspera($paciente, $profissional, $especialidadeId, $inicio, $motivo, $por, $aceitaEspera);
        }

        try {
            return $this->registar($paciente, $profissional, $especialidadeId, $inicio, $motivo, $por);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'inicio' => __('Já tem uma consulta a essa hora, ou a vaga acabou de ser ocupada.'),
            ]);
        }
    }

    /**
     * A receção marca em nome do paciente: a consulta nasce "pedida" e passa logo a "confirmada"
     * na mesma transação, para a matriz de transições se manter e o histórico ter os dois passos (D24).
     * Numa vaga ocupada, e se a receção o indicar, o paciente fica "em_espera".
     *
     * @throws ValidationException
     */
    public function marcarPelaRececao(Paciente $paciente, Profissional $profissional, int $especialidadeId, CarbonImmutable $inicio, ?string $motivo, User $rececionista, bool $aceitaEspera = false): Consulta
    {
        $this->validarEspecialidade($profissional, $especialidadeId);

        if (! $this->disponibilidade->estaLivre($profissional, $inicio)) {
            return $this->entrarEmEspera($paciente, $profissional, $especialidadeId, $inicio, $motivo, $rececionista, $aceitaEspera);
        }

        try {
            return DB::transaction(function () use ($paciente, $profissional, $especialidadeId, $inicio, $motivo, $rececionista) {
                $consulta = $this->registar($paciente, $profissional, $especialidadeId, $inicio, $motivo, $rececionista);
                $consulta->mudarEstado(EstadoConsulta::Confirmada, $rececionista);

                return $consulta;
            });
        } catch (UniqueConstraintViolationException) {
            // A vaga foi ocupada entre a verificação e a gravação, ou o paciente
            // já tem uma consulta a essa hora com outro profissional.
            throw ValidationException::withMessages([
                'inicio' => __('O paciente já tem uma consulta a essa hora, ou a vaga acabou de ser ocupada.'),
            ]);
        }
    }

    /**
     * A receção rejeita um pedido, com motivo, e a lista de espera é promovida na mesma transação (D32).
     *
     * @throws TransicaoEstadoInvalida se o pedido já não estiver pendente
     */
    public function rejeitar(Consulta $consulta, User $por, string $motivo): void
    {
        DB::transaction(function () use ($consulta, $por, $motivo): void {
            $consulta->mudarEstado(EstadoConsulta::Rejeitada, $por, $motivo);

            $this->promoverListaEspera($consulta->profissional, $consulta->inicio->toImmutable());
        });
    }

    /**
     * Reagenda: na mesma transação, a consulta antiga é cancelada com o motivo "Reagendada" e
     * nasce uma nova na vaga escolhida, ligada à antiga (D31). A nova herda o estado da antiga
     * (pendente continua pendente, confirmada nasce confirmada), a vaga antiga fica livre e a
     * sua lista de espera é promovida (D32). A vaga nova tem de estar livre: reagendar não põe
     * ninguém em lista de espera.
     *
     * @throws ValidationException se a vaga nova não servir
     * @throws TransicaoEstadoInvalida se a consulta já não estiver pendente nem confirmada
     */
    public function reagendar(Consulta $antiga, Profissional $profissional, CarbonImmutable $inicio, User $por): Consulta
    {
        if (! $this->podeSerReagendada($antiga)) {
            throw new TransicaoEstadoInvalida($antiga->estado, EstadoConsulta::Cancelada);
        }

        $this->validarEspecialidade($profissional, $antiga->especialidade_id);

        if (! $this->disponibilidade->estaLivre($profissional, $inicio)) {
            throw ValidationException::withMessages([
                'inicio' => __('Esta vaga já não está disponível.'),
            ]);
        }

        try {
            return DB::transaction(function () use ($antiga, $profissional, $inicio, $por) {
                $estavaConfirmada = $antiga->estado === EstadoConsulta::Confirmada;

                // Primeiro liberta-se a vaga antiga: o paciente pode estar a mudar só de profissional, à mesma hora.
                $antiga->mudarEstado(EstadoConsulta::Cancelada, $por, 'Reagendada');

                $nova = $this->registar($antiga->paciente, $profissional, $antiga->especialidade_id, $inicio, $antiga->motivo_consulta, $por);
                $nova->forceFill(['reagendada_de_id' => $antiga->id])->save();

                if ($estavaConfirmada) {
                    $nova->mudarEstado(EstadoConsulta::Confirmada, $por);
                }

                $this->promoverListaEspera($antiga->profissional, $antiga->inicio->toImmutable());

                return $nova;
            });
        } catch (UniqueConstraintViolationException) {
            // A transação desfez tudo: a consulta antiga continua como estava.
            throw ValidationException::withMessages([
                'inicio' => __('O paciente já tem uma consulta a essa hora, ou a vaga acabou de ser ocupada.'),
            ]);
        }
    }

    /** Reagendam-se pedidos pendentes e consultas confirmadas que ainda não começaram. */
    public function podeSerReagendada(Consulta $consulta): bool
    {
        return in_array($consulta->estado, [EstadoConsulta::Pedida, EstadoConsulta::Confirmada], true)
            && $consulta->inicio->isFuture();
    }

    /**
     * Cancela a consulta e, se ela ocupava a vaga, promove a lista de espera na mesma transação (D32).
     * Quem pode cancelar é com a ConsultaPolicy; os prazos de cada perfil estão nos dois métodos abaixo.
     *
     * @throws TransicaoEstadoInvalida se a consulta já não puder ser cancelada
     */
    public function cancelar(Consulta $consulta, ?User $por, ?string $motivo = null): void
    {
        DB::transaction(function () use ($consulta, $por, $motivo): void {
            $ocupavaVaga = $consulta->estado->ocupaVaga();

            $consulta->mudarEstado(EstadoConsulta::Cancelada, $por, $motivo);

            if ($ocupavaVaga) {
                $this->promoverListaEspera($consulta->profissional, $consulta->inicio->toImmutable());
            }
        });
    }

    /**
     * O paciente cancela um pedido ou uma consulta confirmada até 24 horas antes, e sai da
     * lista de espera até à hora da vaga (D34).
     */
    public function pacientePodeCancelar(Consulta $consulta): bool
    {
        return match ($consulta->estado) {
            EstadoConsulta::EmEspera => $consulta->inicio->isFuture(),
            EstadoConsulta::Pedida, EstadoConsulta::Confirmada => now()->addHours(self::HORAS_ANTECEDENCIA_PACIENTE)->lessThanOrEqualTo($consulta->inicio),
            default => false,
        };
    }

    /**
     * O profissional cancela por indisponibilidade as suas consultas confirmadas que ainda não
     * começaram. Os pedidos pendentes são da receção; depois da hora regista-se realizada ou falta (D34).
     */
    public function profissionalPodeCancelar(Consulta $consulta): bool
    {
        return $consulta->estado === EstadoConsulta::Confirmada && $consulta->inicio->isFuture();
    }

    /**
     * Fecha os pedidos em espera cuja vaga já passou: nunca vão ser atendidos (D33).
     *
     * @return int quantos pedidos foram cancelados
     */
    public function expirarEspera(): int
    {
        $expirados = Consulta::where('estado', EstadoConsulta::EmEspera)->where('inicio', '<', now())->get();

        $expirados->each(fn (Consulta $consulta) => $consulta->mudarEstado(EstadoConsulta::Cancelada, null, 'Vaga expirada'));

        return $expirados->count();
    }

    /**
     * Cancela os pedidos em espera para as vagas futuras de um período de horário que vai ser
     * removido: sem o período, essas vagas deixam de existir e os pedidos ficavam órfãos.
     */
    public function cancelarEsperaDoPeriodo(Horario $horario): void
    {
        Consulta::where('estado', EstadoConsulta::EmEspera)
            ->where('profissional_id', $horario->profissional_id)
            ->where('inicio', '>', now())
            ->get()
            ->filter(fn (Consulta $consulta) => $consulta->inicio->isoWeekday() === $horario->dia_semana
                && $consulta->inicio->format('H:i:s') >= $horario->hora_inicio
                && $consulta->inicio->format('H:i:s') < $horario->hora_fim)
            ->each(fn (Consulta $consulta) => $consulta->mudarEstado(EstadoConsulta::Cancelada, null, 'Horário removido'));
    }

    /** @throws ValidationException */
    private function validarEspecialidade(Profissional $profissional, int $especialidadeId): void
    {
        if (! $profissional->especialidades()->whereKey($especialidadeId)->exists()) {
            throw ValidationException::withMessages([
                'especialidade_id' => __('O profissional escolhido não exerce esta especialidade.'),
            ]);
        }
    }

    /**
     * A vaga não está livre. Só se entra em espera se quem pede o aceitou, se a vaga existir e
     * estiver mesmo ocupada, e se o paciente não tiver já uma consulta ou um pedido para essa hora.
     *
     * @throws ValidationException
     */
    private function entrarEmEspera(Paciente $paciente, Profissional $profissional, int $especialidadeId, CarbonImmutable $inicio, ?string $motivo, User $por, bool $aceitaEspera): Consulta
    {
        if (! $aceitaEspera || ! $this->disponibilidade->estaOcupada($profissional, $inicio)) {
            throw ValidationException::withMessages([
                'inicio' => __('Esta vaga já não está disponível.'),
            ]);
        }

        $doPaciente = Consulta::where('paciente_id', $paciente->id)->where('inicio', $inicio);

        if ((clone $doPaciente)->whereNotNull('vaga_ocupada')->exists()) {
            throw ValidationException::withMessages([
                'inicio' => __('Já existe uma consulta deste paciente a essa hora.'),
            ]);
        }

        if ($doPaciente->where('estado', EstadoConsulta::EmEspera)->where('profissional_id', $profissional->id)->exists()) {
            throw ValidationException::withMessages([
                'inicio' => __('Este paciente já está na lista de espera desta vaga.'),
            ]);
        }

        return $this->registar($paciente, $profissional, $especialidadeId, $inicio, $motivo, $por, EstadoConsulta::EmEspera);
    }

    private function registar(Paciente $paciente, Profissional $profissional, int $especialidadeId, CarbonImmutable $inicio, ?string $motivo, User $por, EstadoConsulta $estado = EstadoConsulta::Pedida): Consulta
    {
        return Consulta::registarPedido([
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidadeId,
            'inicio' => $inicio,
            'motivo_consulta' => $motivo,
        ], $estado, $por);
    }

    /**
     * Uma vaga ficou livre: o primeiro pedido em espera para ela passa a "pedida", em nome do
     * sistema (D32). É chamado dentro da transação que liberta a vaga, e bloqueia os pedidos em
     * espera dessa vaga para dois cancelamentos simultâneos não promoverem o mesmo pedido.
     *
     * Se o primeiro da lista já tiver outra consulta a essa hora, a base de dados recusa a
     * promoção; cada tentativa corre num savepoint próprio, para a recusa não desfazer a
     * transação de fora, e passa-se ao seguinte.
     */
    private function promoverListaEspera(Profissional $profissional, CarbonImmutable $inicio): void
    {
        // Uma vaga que já passou não se oferece a ninguém (D33).
        if ($inicio->isPast()) {
            return;
        }

        $emEspera = Consulta::emEspera()
            ->where('profissional_id', $profissional->id)
            ->where('inicio', $inicio)
            ->lockForUpdate()
            ->get();

        foreach ($emEspera as $pedido) {
            try {
                DB::transaction(fn () => $pedido->mudarEstado(EstadoConsulta::Pedida, null));

                return;
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }
    }
}
