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
