<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pacientes', function (Blueprint $table) {
            $table->id();
            // Nulo quando o paciente foi criado ao balcão e ainda não tem conta.
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('nome');
            // Identifica o paciente quando a rececionista lhe associa uma conta e evita fichas duplicadas.
            $table->string('num_utente', 9)->unique();
            $table->string('telefone', 20)->nullable();
            $table->string('email_contacto')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pacientes');
    }
};
