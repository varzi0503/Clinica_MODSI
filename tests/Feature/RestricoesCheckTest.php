<?php

namespace Tests\Feature;

use App\Models\Consulta;
use App\Models\Horario;
use App\Models\Profissional;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * As restrições CHECK da base de dados (horas certas ou meias, dia da semana, intervalo do horário).
 * Só existem em MySQL e MariaDB, com qualquer dos dois drivers do Laravel (D38); em SQLite estes
 * testes são saltados. Correr contra MySQL ou MariaDB com DB_CONNECTION=mysql ou mariadb.
 */
class RestricoesCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('As restrições CHECK só existem em MySQL e MariaDB.');
        }
    }

    public function test_consulta_fora_de_uma_hora_certa_ou_meia_e_recusada_pela_base_de_dados(): void
    {
        $this->expectException(QueryException::class);

        Consulta::factory()->confirmada()->create(['inicio' => '2026-11-30 10:15:00']);
    }

    public function test_horario_com_dia_invalido_intervalo_ao_contrario_ou_minutos_soltos_e_recusado(): void
    {
        $profissional = Profissional::factory()->create();
        $invalidos = [
            ['dia_semana' => 8, 'hora_inicio' => '09:00', 'hora_fim' => '10:00'],
            ['dia_semana' => 1, 'hora_inicio' => '12:00', 'hora_fim' => '09:00'],
            ['dia_semana' => 2, 'hora_inicio' => '09:15', 'hora_fim' => '10:00'],
        ];

        foreach ($invalidos as $dados) {
            try {
                Horario::factory()->for($profissional)->create($dados);
                $this->fail('A base de dados aceitou um horário inválido: '.json_encode($dados));
            } catch (QueryException) {
                // Esperado.
            }
        }

        $this->assertSame(0, $profissional->horarios()->count());
    }
}
