<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'nome', 'num_utente', 'telefone', 'email_contacto'])]
class Paciente extends Model
{
    /** Nulo quando o paciente foi criado ao balcão e ainda não tem conta. */
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Consulta, $this> */
    public function consultas(): HasMany
    {
        return $this->hasMany(Consulta::class);
    }
}
