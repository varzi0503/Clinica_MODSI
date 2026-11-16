<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * D38: a base de dados de entrega fica num servidor MariaDB. As migrations dos horários e das
 * consultas só criam as restrições CHECK quando o driver se chama "mysql"; com o driver "mariadb"
 * do Laravel ficavam sem elas. Esta migration cria-as nesse caso. Com o driver "mysql" (em MySQL
 * ou em MariaDB) já existem, e em SQLite não se aplicam.
 */
return new class extends Migration
{
    /** @var array<string, array{0: string, 1: string}> nome => [tabela, condição] */
    private const RESTRICOES = [
        'chk_horarios_dia' => ['horarios', 'dia_semana BETWEEN 1 AND 7'],
        'chk_horarios_intervalo' => ['horarios', 'hora_fim > hora_inicio'],
        'chk_horarios_vagas_30min' => ['horarios', 'MINUTE(hora_inicio) IN (0, 30) AND SECOND(hora_inicio) = 0 AND MINUTE(hora_fim) IN (0, 30) AND SECOND(hora_fim) = 0'],
        'chk_consultas_vaga_30min' => ['consultas', 'MINUTE(inicio) IN (0, 30) AND SECOND(inicio) = 0'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mariadb') {
            return;
        }

        foreach (self::RESTRICOES as $nome => [$tabela, $condicao]) {
            DB::statement("ALTER TABLE {$tabela} ADD CONSTRAINT {$nome} CHECK ({$condicao})");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mariadb') {
            return;
        }

        foreach (self::RESTRICOES as $nome => [$tabela]) {
            DB::statement("ALTER TABLE {$tabela} DROP CONSTRAINT {$nome}");
        }
    }
};
