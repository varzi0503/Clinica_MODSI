<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// ativo, tentativas_falhadas e bloqueado_ate ficam fora do Fillable de propósito:
// só a lógica de autenticação os altera, nunca dados vindos de um formulário.
#[Fillable(['perfil_id', 'name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ativo' => 'boolean',
            'tentativas_falhadas' => 'integer',
            'bloqueado_ate' => 'datetime',
        ];
    }

    /** @return BelongsTo<Perfil, $this> */
    public function perfil(): BelongsTo
    {
        return $this->belongsTo(Perfil::class);
    }

    /** @return HasOne<Profissional, $this> */
    public function profissional(): HasOne
    {
        return $this->hasOne(Profissional::class);
    }

    /** @return HasOne<Paciente, $this> */
    public function paciente(): HasOne
    {
        return $this->hasOne(Paciente::class);
    }

    public function temPerfil(string ...$nomes): bool
    {
        return in_array($this->perfil?->nome, $nomes, true);
    }
}
