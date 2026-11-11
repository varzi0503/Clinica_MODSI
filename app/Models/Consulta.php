<?php

namespace App\Models;

use App\Enums\EstadoConsulta;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

// Sem created_at/updated_at: a tabela usa data_pedido e data_estado.
// vaga_ocupada é uma coluna gerada pela base de dados: nunca se escreve.
// estado e data_estado também ficam fora do Fillable: mudam só através de mudarEstado().
#[WithoutTimestamps]
#[Fillable(['paciente_id', 'profissional_id', 'especialidade_id', 'inicio', 'motivo_consulta'])]
class Consulta extends Model
{
    protected function casts(): array
    {
        return [
            'estado' => EstadoConsulta::class,
            'inicio' => 'datetime',
            'data_pedido' => 'datetime',
            'data_estado' => 'datetime',
        ];
    }

    /** @return BelongsTo<Paciente, $this> */
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    /** @return BelongsTo<Profissional, $this> */
    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Profissional::class);
    }

    /** @return BelongsTo<Especialidade, $this> */
    public function especialidade(): BelongsTo
    {
        return $this->belongsTo(Especialidade::class);
    }

    /** @return HasMany<HistoricoEstado, $this> */
    public function historico(): HasMany
    {
        return $this->hasMany(HistoricoEstado::class)->orderBy('data_hora')->orderBy('id');
    }

    /** Lista de espera de uma vaga, por ordem de pedido. */
    #[Scope]
    protected function emEspera(Builder $query): void
    {
        $query->where('estado', EstadoConsulta::EmEspera)->orderBy('data_pedido')->orderBy('id');
    }

    /**
     * Cria o pedido e o primeiro registo do histórico na mesma transação.
     *
     * @param  array<string, mixed>  $dados
     */
    public static function registarPedido(array $dados, EstadoConsulta $estado, ?User $por): self
    {
        return DB::transaction(function () use ($dados, $estado, $por) {
            $consulta = new self($dados);
            $consulta->estado = $estado;
            $consulta->data_pedido = $consulta->data_estado = now();
            $consulta->save();

            $consulta->registarHistorico($por, null);

            return $consulta;
        });
    }

    /**
     * Muda o estado e regista-o no histórico na mesma transação, para que consultas.estado
     * nunca fique diferente do último registo do histórico.
     * $por é nulo quando a mudança é feita pelo sistema (ex.: promoção da lista de espera).
     * Não valida se a transição é permitida: isso fica para cada caso de uso.
     */
    public function mudarEstado(EstadoConsulta $novo, ?User $por, ?string $motivo = null): void
    {
        DB::transaction(function () use ($novo, $por, $motivo) {
            $this->estado = $novo;
            $this->data_estado = now();
            $this->save();

            $this->registarHistorico($por, $motivo);
        });
    }

    private function registarHistorico(?User $por, ?string $motivo): void
    {
        $this->historico()->create([
            'user_id' => $por?->id,
            'estado' => $this->estado,
            'data_hora' => $this->data_estado,
            'motivo' => $motivo,
        ]);
    }
}
