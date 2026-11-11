<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Períodos de atendimento semanais que se repetem (não por data).
        Schema::create('horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profissional_id')->constrained('profissionais')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana'); // 1 = segunda ... 7 = domingo
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->timestamps();

            $table->unique(['profissional_id', 'dia_semana', 'hora_inicio']);
        });

        // Restrições CHECK só no MySQL (o SQLite dos testes não permite acrescentá-las depois).
        // A sobreposição de períodos valida-se na aplicação: o MySQL não a consegue exprimir num CHECK.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE horarios ADD CONSTRAINT chk_horarios_dia CHECK (dia_semana BETWEEN 1 AND 7)');
            DB::statement('ALTER TABLE horarios ADD CONSTRAINT chk_horarios_intervalo CHECK (hora_fim > hora_inicio)');
            DB::statement('ALTER TABLE horarios ADD CONSTRAINT chk_horarios_vagas_30min CHECK (
                MINUTE(hora_inicio) IN (0, 30) AND SECOND(hora_inicio) = 0
                AND MINUTE(hora_fim) IN (0, 30) AND SECOND(hora_fim) = 0
            )');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('horarios');
    }
};
