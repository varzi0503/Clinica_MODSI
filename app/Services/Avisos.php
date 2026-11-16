<?php

namespace App\Services;

use App\Models\Consulta;
use App\Models\User;
use App\Notifications\ConsultaAtualizada;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Quem é avisado de quê (issue #74, D36). Ninguém é avisado do que ele próprio fez.
 *
 * Os avisos só saem depois de a transação que mudou a consulta ficar gravada: se ela for
 * desfeita (por exemplo, num conflito de vaga), ninguém recebe um email de algo que não aconteceu.
 */
class Avisos
{
    /**
     * Avisa o paciente. Com conta, fica na aplicação e segue por email; sem conta, segue só
     * por email para o email de contacto da ficha, se existir.
     *
     * @param  ConsultaAtualizada::*  $evento
     */
    public function aoPaciente(Consulta $consulta, string $evento, ?User $autor, ?string $motivo = null): void
    {
        $conta = $consulta->paciente->user;

        if ($conta !== null && $conta->is($autor)) {
            return;
        }

        $aviso = new ConsultaAtualizada($consulta, $evento, $motivo);

        DB::afterCommit(function () use ($consulta, $conta, $aviso): void {
            if ($conta !== null) {
                $conta->notify($aviso);
            } elseif ($consulta->paciente->email_contacto !== null) {
                Notification::route('mail', $consulta->paciente->email_contacto)->notify($aviso);
            }
        });
    }

    /**
     * Avisa o profissional de que uma consulta confirmada sua foi cancelada ou reagendada por outra pessoa.
     *
     * @param  ConsultaAtualizada::CANCELADA|ConsultaAtualizada::REAGENDADA  $evento
     */
    public function aoProfissional(Consulta $consulta, string $evento, ?User $autor, ?string $motivo = null): void
    {
        $conta = $consulta->profissional->user;

        if ($conta->is($autor)) {
            return;
        }

        $aviso = new ConsultaAtualizada($consulta, $evento, $motivo, paraProfissional: true);

        DB::afterCommit(fn () => $conta->notify($aviso));
    }
}
