<?php

namespace App\Services;

use App\Enums\EstadoConsulta;
use App\Exceptions\TransicaoEstadoInvalida;
use App\Models\Consulta;
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
 *
 * Rejeitar e reagendar entram aqui com os respetivos casos de uso (#67, #75).
 */
class Marcacoes
{
    /** Antecedência mínima, em horas, para o paciente cancelar uma consulta que ocupa a vaga (D34). */
    public const HORAS_ANTECEDENCIA_PACIENTE = 24;

    public function __construct(private readonly Disponibilidade $disponibilidade) {}

    /**
     * O paciente pede uma consulta: fica "pedida" até a receção a confirmar (D24).
     *
     * @throws ValidationException
     */
    public function pedir(Paciente $paciente, Profissional $profissional, int $especialidadeId, CarbonImmutable $inicio, ?string $motivo, User $por): Consulta
    {
        $this->validarVaga($profissional, $especialidadeId, $inicio);

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
     *
     * @throws ValidationException
     */
    public function marcarPelaRececao(Paciente $paciente, Profissional $profissional, int $especialidadeId, CarbonImmutable $inicio, ?string $motivo, User $rececionista): Consulta
    {
        $this->validarVaga($profissional, $especialidadeId, $inicio);

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

    /** @throws ValidationException */
    private function validarVaga(Profissional $profissional, int $especialidadeId, CarbonImmutable $inicio): void
    {
        if (! $profissional->especialidades()->whereKey($especialidadeId)->exists()) {
            throw ValidationException::withMessages([
                'especialidade_id' => __('O profissional escolhido não exerce esta especialidade.'),
            ]);
        }

        if (! $this->disponibilidade->estaLivre($profissional, $inicio)) {
            throw ValidationException::withMessages([
                'inicio' => __('Esta vaga já não está disponível.'),
            ]);
        }
    }

    private function registar(Paciente $paciente, Profissional $profissional, int $especialidadeId, CarbonImmutable $inicio, ?string $motivo, User $por): Consulta
    {
        return Consulta::registarPedido([
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidadeId,
            'inicio' => $inicio,
            'motivo_consulta' => $motivo,
        ], EstadoConsulta::Pedida, $por);
    }

    /**
     * Ponto de extensão para a issue #75: quando uma vaga se liberta (cancelar, rejeitar,
     * reagendar), o primeiro pedido em espera para essa vaga passa a "pedida". Tem de ser
     * chamado dentro da mesma transação que liberta a vaga (D32).
     */
    private function promoverListaEspera(Profissional $profissional, CarbonImmutable $inicio): void
    {
        // Por implementar no #75.
    }
}
