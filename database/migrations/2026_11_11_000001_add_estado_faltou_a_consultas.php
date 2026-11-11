<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #4: estado "faltou" para o UC13 (registar falta) e estatísticas (UC25).
 * Uma falta consome a vaga tal como uma consulta realizada, por isso a coluna
 * gerada vaga_ocupada passa a incluí-la.
 */
return new class extends Migration
{
    private const COM_FALTOU = ['em_espera', 'pedida', 'confirmada', 'rejeitada', 'cancelada', 'realizada', 'faltou'];

    private const SEM_FALTOU = ['em_espera', 'pedida', 'confirmada', 'rejeitada', 'cancelada', 'realizada'];

    public function up(): void
    {
        $this->alterarEstados(self::COM_FALTOU, "('pedida', 'confirmada', 'realizada', 'faltou')");
    }

    public function down(): void
    {
        // O enum não pode encolher com linhas 'faltou' lá dentro; a aproximação
        // possível é reclassificá-las como canceladas antes de reverter.
        DB::table('consultas')->where('estado', 'faltou')->update(['estado' => 'cancelada']);
        DB::table('historico_estados')->where('estado', 'faltou')->update(['estado' => 'cancelada']);

        $this->alterarEstados(self::SEM_FALTOU, "('pedida', 'confirmada', 'realizada')");
    }

    /** @param  list<string>  $estados */
    private function alterarEstados(array $estados, string $estadosQueOcupam): void
    {
        $formula = "case when estado in {$estadosQueOcupam} then 1 else null end";

        if (DB::getDriverName() === 'sqlite') {
            // O SQLite reconstrói a tabela inteira num change() (índices e FKs sobrevivem)
            // e não tem a restrição do MySQL sobre colunas geradas, mas também não
            // aceita ADD COLUMN ... STORED: por isso tudo aqui é change(), nunca drop + add.
            // As duas alterações vão no MESMO blueprint: numa segunda reconstrução,
            // o estado seria relido do esquema como varchar e o CHECK do enum perdia-se.
            Schema::table('consultas', function (Blueprint $table) use ($estados, $formula) {
                $table->enum('estado', $estados)->change();
                $table->unsignedTinyInteger('vaga_ocupada')->nullable()->storedAs($formula)->change();
            });
            Schema::table('historico_estados', fn (Blueprint $table) => $table->enum('estado', $estados)->change());

            return;
        }

        // MySQL não deixa alterar uma coluna de que uma coluna gerada depende,
        // logo a vaga_ocupada e os índices únicos saem e voltam a entrar à volta
        // da alteração do enum.
        //
        // A FK de paciente_id apoia-se no índice único composto; sem um índice
        // próprio para ela, o DROP INDEX é recusado (erro 1553).
        if (! Schema::hasIndex('consultas', 'consultas_paciente_id_index')) {
            Schema::table('consultas', fn (Blueprint $table) => $table->index('paciente_id'));
        }

        Schema::table('consultas', function (Blueprint $table) {
            $table->dropUnique('consultas_vaga_profissional_unique');
            $table->dropUnique('consultas_vaga_paciente_unique');
            $table->dropColumn('vaga_ocupada');
        });

        Schema::table('consultas', fn (Blueprint $table) => $table->enum('estado', $estados)->change());
        Schema::table('historico_estados', fn (Blueprint $table) => $table->enum('estado', $estados)->change());

        Schema::table('consultas', function (Blueprint $table) use ($formula) {
            $table->unsignedTinyInteger('vaga_ocupada')->nullable()->storedAs($formula);
            $table->unique(['profissional_id', 'inicio', 'vaga_ocupada'], 'consultas_vaga_profissional_unique');
            $table->unique(['paciente_id', 'inicio', 'vaga_ocupada'], 'consultas_vaga_paciente_unique');
        });
    }
};
