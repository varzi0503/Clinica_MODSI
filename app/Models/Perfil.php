<?php

namespace App\Models;

use Database\Factories\PerfilFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// O Laravel pluraliza em inglês ("perfils"), por isso a tabela é indicada à mão.
#[Table('perfis')]
#[Fillable(['nome'])]
class Perfil extends Model
{
    /** @use HasFactory<PerfilFactory> */
    use HasFactory;

    public const ADMINISTRADOR = 'administrador';

    public const RECECIONISTA = 'rececionista';

    public const PROFISSIONAL = 'profissional';

    public const PACIENTE = 'paciente';

    /** Os perfis que existem: o acesso nas rotas está ligado a estes nomes. */
    public const NOMES = [self::ADMINISTRADOR, self::RECECIONISTA, self::PROFISSIONAL, self::PACIENTE];

    /**
     * Perfis que o administrador atribui. Profissionais são criados pela receção
     * com a ficha (UC22); pacientes pelo registo ou pela receção.
     */
    public const ATRIBUIVEIS_PELO_ADMIN = [self::ADMINISTRADOR, self::RECECIONISTA];

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
