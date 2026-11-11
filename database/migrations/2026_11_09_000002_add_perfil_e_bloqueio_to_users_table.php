<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A tabela users já existe no main, por isso altera-se aqui em vez de editar a migration original.
        // O perfil_id é criado nullable e só depois passa a obrigatório: o SQLite (usado nos testes)
        // não aceita ADD COLUMN NOT NULL sem valor por omissão.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('perfil_id')->nullable()->after('id')->constrained('perfis')->restrictOnDelete();
            $table->boolean('ativo')->default(true)->after('password');
            $table->unsignedSmallInteger('tentativas_falhadas')->default(0)->after('ativo');
            $table->timestamp('bloqueado_ate')->nullable()->after('tentativas_falhadas');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('perfil_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['perfil_id']);
            $table->dropColumn(['perfil_id', 'ativo', 'tentativas_falhadas', 'bloqueado_ate']);
        });
    }
};
