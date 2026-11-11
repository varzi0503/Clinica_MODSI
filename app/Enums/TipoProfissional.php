<?php

namespace App\Enums;

enum TipoProfissional: string
{
    case Medico = 'medico';
    case Enfermeiro = 'enfermeiro';

    public function label(): string
    {
        return match ($this) {
            self::Medico => __('Médico'),
            self::Enfermeiro => __('Enfermeiro'),
        };
    }
}
