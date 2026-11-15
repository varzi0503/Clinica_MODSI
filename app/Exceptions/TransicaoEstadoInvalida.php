<?php

namespace App\Exceptions;

use App\Enums\EstadoConsulta;
use DomainException;

class TransicaoEstadoInvalida extends DomainException
{
    public function __construct(public readonly EstadoConsulta $de, public readonly EstadoConsulta $para)
    {
        parent::__construct("Transição de estado inválida: {$de->value} -> {$para->value}.");
    }

    /**
     * O que se mostra a quem tentou a transição. Depende do que se queria fazer ($para),
     * porque o motivo habitual é outra pessoa ter tratado a consulta entretanto.
     */
    public function mensagemParaUtilizador(): string
    {
        return match ($this->para) {
            EstadoConsulta::Confirmada, EstadoConsulta::Rejeitada => __('Este pedido já não está pendente.'),
            EstadoConsulta::Realizada, EstadoConsulta::Faltou => __('Só é possível registar consultas confirmadas.'),
            default => __('Esta consulta já mudou de estado. Atualize a página e tente de novo.'),
        };
    }
}
