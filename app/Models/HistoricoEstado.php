<?php

namespace App\Models;

use App\Enums\EstadoConsulta;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Sem created_at/updated_at: a tabela usa data_hora.
#[WithoutTimestamps]
#[Fillable(['consulta_id', 'user_id', 'estado', 'data_hora', 'motivo'])]
class HistoricoEstado extends Model
{
    protected function casts(): array
    {
        return [
            'estado' => EstadoConsulta::class,
            'data_hora' => 'datetime',
        ];
    }

    /** @return BelongsTo<Consulta, $this> */
    public function consulta(): BelongsTo
    {
        return $this->belongsTo(Consulta::class);
    }

    /** Nulo quando a mudança foi feita pelo sistema. */
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
