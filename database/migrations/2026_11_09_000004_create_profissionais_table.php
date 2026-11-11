<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profissionais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            // Única fonte do tipo de profissional. O perfil é sempre "profissional" para ambos.
            $table->enum('tipo', ['medico', 'enfermeiro']);
            $table->string('num_cedula', 20);
            $table->string('contacto', 20)->nullable();
            $table->timestamps();

            // As Ordens dos Médicos e dos Enfermeiros numeram de forma independente.
            $table->unique(['tipo', 'num_cedula']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profissionais');
    }
};
