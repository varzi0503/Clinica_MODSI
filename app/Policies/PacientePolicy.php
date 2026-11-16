<?php

namespace App\Policies;

use App\Models\Paciente;
use App\Models\User;

/** Quem pode ver dados de um paciente (RNF03, issue #57). */
class PacientePolicy
{
    /**
     * Um profissional só vê o histórico dos seus pacientes: os que têm, ou tiveram,
     * pelo menos uma consulta com ele.
     */
    public function verHistorico(User $user, Paciente $paciente): bool
    {
        $profissional = $user->profissional;

        return $profissional !== null
            && $paciente->consultas()->where('profissional_id', $profissional->id)->exists();
    }
}
