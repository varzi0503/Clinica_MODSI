<?php

namespace App\Policies;

use App\Models\Documento;
use App\Models\User;

/** Quem descarrega e quem apaga um documento de uma consulta (D37). */
class DocumentoPolicy
{
    /** Descarrega quem pode ver os documentos da consulta: o paciente e o profissional dela. */
    public function descarregar(User $user, Documento $documento): bool
    {
        return $user->can('verDocumentos', $documento->consulta);
    }

    /** Só apaga quem o carregou. */
    public function apagar(User $user, Documento $documento): bool
    {
        return $documento->user_id === $user->id && $this->descarregar($user, $documento);
    }
}
