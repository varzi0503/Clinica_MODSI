<?php

namespace App\Models;

use App\Enums\TipoProfissional;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// O Laravel pluraliza em inglês ("profissionals"), por isso a tabela é indicada à mão.
#[Table('profissionais')]
#[Fillable(['user_id', 'tipo', 'num_cedula', 'contacto'])]
class Profissional extends Model
{
    protected function casts(): array
    {
        return [
            'tipo' => TipoProfissional::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Especialidade, $this> */
    public function especialidades(): BelongsToMany
    {
        return $this->belongsToMany(Especialidade::class);
    }

    /** @return HasMany<Horario, $this> */
    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class);
    }

    /** @return HasMany<Consulta, $this> */
    public function consultas(): HasMany
    {
        return $this->hasMany(Consulta::class);
    }
}
