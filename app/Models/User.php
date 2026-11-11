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

    public const MAX_TENTATIVAS = 5;

    public const MINUTOS_BLOQUEIO = 15;

    public function estaBloqueado(): bool
    {
        return $this->bloqueado_ate !== null && $this->bloqueado_ate->isFuture();
    }

    /** Conta a falha e, à quinta, bloqueia a conta por 15 minutos. */
    public function registarLoginFalhado(): void
    {
        $this->tentativas_falhadas++;

        if ($this->tentativas_falhadas >= self::MAX_TENTATIVAS) {
            $this->bloqueado_ate = now()->addMinutes(self::MINUTOS_BLOQUEIO);
            $this->tentativas_falhadas = 0;
        }

        $this->save();
    }

    public function registarLoginComSucesso(): void
    {
        $this->forceFill(['tentativas_falhadas' => 0, 'bloqueado_ate' => null])->save();
    }
}
