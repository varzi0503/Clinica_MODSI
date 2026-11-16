<?php

namespace App\Models;

use Database\Factories\DocumentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ficheiro associado a uma consulta, carregado pelo paciente (análises, exames) ou pelo
 * profissional (receita, relatório). Veem-no os dois e mais ninguém (D37).
 */
#[Fillable(['consulta_id', 'user_id', 'nome_original', 'caminho', 'mime', 'tamanho'])]
class Documento extends Model
{
    /** @use HasFactory<DocumentoFactory> */
    use HasFactory;

    /** Disco privado: os ficheiros só saem pelo controlador, depois de verificada a policy. */
    public const DISCO = 'local';

    public const TIPOS = ['pdf', 'jpg', 'jpeg', 'png'];

    public const TAMANHO_MAXIMO_KB = 5120;

    protected function casts(): array
    {
        return ['tamanho' => 'integer'];
    }

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

    /** Tamanho legível, por exemplo "340 KB" ou "1,2 MB". */
    public function tamanhoLegivel(): string
    {
        return $this->tamanho >= 1024 * 1024
            ? number_format($this->tamanho / (1024 * 1024), 1, ',', '').' MB'
            : max(1, (int) round($this->tamanho / 1024)).' KB';
    }
}
