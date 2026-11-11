<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historico_estados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consulta_id')->constrained('consultas')->cascadeOnDelete();
            // Nulo quando a mudança é feita pelo sistema (ex.: promoção da lista de espera).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('estado', ['em_espera', 'pedida', 'confirmada', 'rejeitada', 'cancelada', 'realizada']);
            $table->dateTime('data_hora');
            $table->string('motivo')->nullable();

            $table->index(['consulta_id', 'data_hora']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historico_estados');
    }
};
