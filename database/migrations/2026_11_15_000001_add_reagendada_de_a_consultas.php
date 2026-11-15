<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D31: reagendar cancela a consulta antiga e cria uma nova, ligada à antiga por esta coluna.
 * Entra antes das vistas SQL (issue #36), que já a incluem; o fluxo de reagendar é da issue #67.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Nula em todas as consultas que não nasceram de um reagendamento.
        if (DB::getDriverName() === 'sqlite') {
            // No SQLite, o Blueprint acrescenta chaves estrangeiras reconstruindo a tabela, e nessa
            // reconstrução o estado é relido como varchar e perde o CHECK do enum (ver a migration
            // do "faltou"). O ADD COLUMN direto aceita a referência e não reconstrói nada.
            DB::statement('alter table consultas add column reagendada_de_id integer null references consultas(id) on delete set null');

            return;
        }

        Schema::table('consultas', function (Blueprint $table) {
            $table->foreignId('reagendada_de_id')->nullable()->constrained('consultas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // O DROP COLUMN do SQLite recusa colunas com chave estrangeira, e reconstruir a tabela
            // perdia o CHECK do enum. Os testes usam uma base em memória, que nunca reverte.
            return;
        }

        Schema::table('consultas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reagendada_de_id');
        });
    }
};
