<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nome', 'descricao'])]
class Especialidade extends Model
{
    /** @return BelongsToMany<Profissional, $this> */
    public function profissionais(): BelongsToMany
    {
        return $this->belongsToMany(Profissional::class);
    }

    /** @return HasMany<Consulta, $this> */
    public function consultas(): HasMany
    {
        return $this->hasMany(Consulta::class);
    }
}
