<?php

namespace App\Enums;

enum EstadoConsulta: string
{
    case EmEspera = 'em_espera';
    case Pedida = 'pedida';
    case Confirmada = 'confirmada';
    case Rejeitada = 'rejeitada';
    case Cancelada = 'cancelada';
    case Realizada = 'realizada';
    case Faltou = 'faltou';

    /**
     * Para onde cada estado pode transitar (issue #61).
     * Rejeitada, cancelada, realizada e faltou são terminais.
     *
     * @return list<self>
     */
    public function transicoesPermitidas(): array
    {
        return match ($this) {
            self::EmEspera => [self::Pedida, self::Cancelada],
            self::Pedida => [self::Confirmada, self::Rejeitada, self::Cancelada],
            self::Confirmada => [self::Realizada, self::Faltou, self::Cancelada],
            self::Rejeitada, self::Cancelada, self::Realizada, self::Faltou => [],
        };
    }

    public function podeTransitarPara(self $novo): bool
    {
        return in_array($novo, $this->transicoesPermitidas(), true);
    }

    /** Estados em que a consulta ocupa a vaga (tem de bater certo com a coluna gerada vaga_ocupada). */
    public function ocupaVaga(): bool
    {
        return in_array($this, [self::Pedida, self::Confirmada, self::Realizada, self::Faltou], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::EmEspera => __('Em espera'),
            self::Pedida => __('Pedida'),
            self::Confirmada => __('Confirmada'),
            self::Rejeitada => __('Rejeitada'),
            self::Cancelada => __('Cancelada'),
            self::Realizada => __('Realizada'),
            self::Faltou => __('Faltou'),
        };
    }
}
