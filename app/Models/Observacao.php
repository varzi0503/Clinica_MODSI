<?php

namespace App\Models;

use Database\Factories\ObservacaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// O Laravel pluraliza em inglês ("observacaos"), por isso a tabela é indicada à mão.
/** Nota clínica do profissional sobre uma consulta. Só ele a vê (D37). */
#[Table('observacoes')]
#[Fillable(['consulta_id', 'user_id', 'texto'])]
class Observacao extends Model
{
    /** @use HasFactory<ObservacaoFactory> */
    use HasFactory;

    /** @return BelongsTo<Consulta, $this> */
    public function consulta(): BelongsTo
    {
        return $this->belongsTo(Consulta::class);
    }

    /** @return BelongsTo<User, $this> */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
