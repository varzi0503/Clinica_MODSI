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
}
