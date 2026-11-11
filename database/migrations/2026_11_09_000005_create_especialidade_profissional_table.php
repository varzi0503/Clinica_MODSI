<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nome da tabela pivot segue a convenção do Laravel: singulares por ordem alfabética.
        Schema::create('especialidade_profissional', function (Blueprint $table) {
            $table->foreignId('profissional_id')->constrained('profissionais')->cascadeOnDelete();
            $table->foreignId('especialidade_id')->constrained('especialidades')->restrictOnDelete();

            // A chave começa por profissional_id porque é esse par que a consulta referencia.
            $table->primary(['profissional_id', 'especialidade_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('especialidade_profissional');
    }
};
