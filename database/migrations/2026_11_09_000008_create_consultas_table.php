<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->restrictOnDelete();
            $table->unsignedBigInteger('profissional_id');
            $table->unsignedBigInteger('especialidade_id');
            $table->dateTime('inicio'); // vaga de 30 min
            $table->enum('estado', ['em_espera', 'pedida', 'confirmada', 'rejeitada', 'cancelada', 'realizada']);
            $table->dateTime('data_pedido');
            $table->dateTime('data_estado');
            $table->text('motivo_consulta')->nullable();

            // 1 enquanto a consulta ocupa a vaga, NULL quando não ocupa. Como o UNIQUE ignora NULLs,
            // vários pedidos em espera, rejeitados ou cancelados podem coexistir na mesma vaga,
            // mas só um pode ocupá-la.
            $table->unsignedTinyInteger('vaga_ocupada')->nullable()->storedAs(
                "case when estado in ('pedida', 'confirmada', 'realizada') then 1 else null end"
            );

            // A especialidade tem de ser uma das que o profissional exerce.
            $table->foreign(['profissional_id', 'especialidade_id'])
                ->references(['profissional_id', 'especialidade_id'])
                ->on('especialidade_profissional')
                ->restrictOnDelete();

            $table->unique(['profissional_id', 'inicio', 'vaga_ocupada'], 'consultas_vaga_profissional_unique');
            $table->unique(['paciente_id', 'inicio', 'vaga_ocupada'], 'consultas_vaga_paciente_unique');
            $table->index(['estado', 'data_pedido']); // lista de espera por ordem de pedido
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE consultas ADD CONSTRAINT chk_consultas_vaga_30min CHECK (
                MINUTE(inicio) IN (0, 30) AND SECOND(inicio) = 0
            )');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('consultas');
    }
};
