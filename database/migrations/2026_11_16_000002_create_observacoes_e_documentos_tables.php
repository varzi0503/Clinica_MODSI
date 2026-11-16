<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #76 (D37): observações clínicas e documentos de uma consulta.
 * Os ficheiros ficam no disco privado; aqui guarda-se só onde estão e quem os carregou.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consulta_id')->constrained('consultas')->cascadeOnDelete();
            // Quem escreveu: o profissional da consulta.
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('texto');
            $table->timestamps();
        });

        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consulta_id')->constrained('consultas')->cascadeOnDelete();
            // Quem carregou: o paciente ou o profissional da consulta.
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('nome_original');
            // Caminho no disco privado; o nome do ficheiro é gerado, nunca o que o utilizador enviou.
            $table->string('caminho')->unique();
            $table->string('mime', 100);
            $table->unsignedInteger('tamanho'); // em bytes
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
        Schema::dropIfExists('observacoes');
    }
};
