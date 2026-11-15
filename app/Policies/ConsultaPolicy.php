<?php

namespace App\Policies;

use App\Models\Consulta;
use App\Models\Perfil;
use App\Models\User;

/**
 * Quem pode fazer o quê a uma consulta (issue #50).
 *
 * Aqui só se decide a posse: de quem é a consulta e que perfil a pode tratar. Se a consulta
 * está num estado que permite a ação é com a matriz de transições (EstadoConsulta), que
 * responde com 409 e não com 403.
 */
class ConsultaPolicy
{
    /** A receção confirma qualquer pedido. */
    public function confirmar(User $user, Consulta $consulta): bool
    {
        return $user->temPerfil(Perfil::RECECIONISTA);
    }

    /** A receção rejeita qualquer pedido. */
    public function rejeitar(User $user, Consulta $consulta): bool
    {
        return $user->temPerfil(Perfil::RECECIONISTA);
    }

    /** Só o profissional da consulta a regista como realizada ou falta (D25). */
    public function registar(User $user, Consulta $consulta): bool
    {
        return $this->eDoProfissional($user, $consulta);
    }

    /** A receção cancela qualquer consulta; o paciente e o profissional, só as suas. */
    public function cancelar(User $user, Consulta $consulta): bool
    {
        return $this->ver($user, $consulta);
    }

    /** A receção vê qualquer consulta; o paciente e o profissional, só as suas (RNF03). */
    public function ver(User $user, Consulta $consulta): bool
    {
        return $user->temPerfil(Perfil::RECECIONISTA)
            || $this->eDoPaciente($user, $consulta)
            || $this->eDoProfissional($user, $consulta);
    }

    private function eDoProfissional(User $user, Consulta $consulta): bool
    {
        $profissional = $user->profissional;

        return $profissional !== null && $consulta->profissional_id === $profissional->id;
    }

    private function eDoPaciente(User $user, Consulta $consulta): bool
    {
        $paciente = $user->paciente;

        return $paciente !== null && $consulta->paciente_id === $paciente->id;
    }
}
