<?php

namespace App\Models;

use Database\Factories\HorarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Período de atendimento semanal que se repete. dia_semana: 1 = segunda ... 7 = domingo. */
#[Fillable(['profissional_id', 'dia_semana', 'hora_inicio', 'hora_fim'])]
class Horario extends Model
{
    /** @use HasFactory<HorarioFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
        ];
    }

    /** @return BelongsTo<Profissional, $this> */
    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Profissional::class);
    }
}
