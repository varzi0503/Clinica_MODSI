<?php

namespace App\Policies;

use App\Models\Horario;
use App\Models\User;

/** Cada profissional só mexe nos períodos do seu próprio horário (issue #50). */
class HorarioPolicy
{
    public function apagar(User $user, Horario $horario): bool
    {
        $profissional = $user->profissional;

        return $profissional !== null && $horario->profissional_id === $profissional->id;
    }
}
