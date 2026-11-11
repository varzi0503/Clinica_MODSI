<?php

namespace Tests\Feature;

use App\Enums\EstadoConsulta;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EstadoFaltouTest extends TestCase
{
    use RefreshDatabase;

    private const AGORA = '2026-11-10 09:00:00';

    private const VAGA = '2026-11-12 10:00:00';

    private int $profissional;

    private int $especialidade;

    private array $pacientes;

    protected function setUp(): void
    {
        parent::setUp();

        $perfil = DB::table('perfis')->insertGetId(['nome' => 'profissional']);
        $user = DB::table('users')->insertGetId(['perfil_id' => $perfil, 'name' => 'Médica', 'email' => 'medica@teste.pt', 'password' => 'x']);
        $this->profissional = DB::table('profissionais')->insertGetId(['user_id' => $user, 'tipo' => 'medico', 'num_cedula' => '123']);
        $this->especialidade = DB::table('especialidades')->insertGetId(['nome' => 'Cardiologia']);
        DB::table('especialidade_profissional')->insert(['profissional_id' => $this->profissional, 'especialidade_id' => $this->especialidade]);
        $this->pacientes = [
            DB::table('pacientes')->insertGetId(['nome' => 'P1', 'num_utente' => '111111111']),
            DB::table('pacientes')->insertGetId(['nome' => 'P2', 'num_utente' => '222222222']),
        ];
    }

    private function consulta(int $paciente, string $estado): int
    {
        return DB::table('consultas')->insertGetId([
            'paciente_id' => $paciente,
            'profissional_id' => $this->profissional,
            'especialidade_id' => $this->especialidade,
            'inicio' => self::VAGA,
            'estado' => $estado,
            'data_pedido' => self::AGORA,
            'data_estado' => self::AGORA,
        ]);
    }

    public function test_faltou_e_um_estado_valido_na_consulta_e_no_historico(): void
    {
        $id = $this->consulta($this->pacientes[0], 'faltou');

        DB::table('historico_estados')->insert([
            'consulta_id' => $id,
            'user_id' => null,
            'estado' => 'faltou',
            'data_hora' => self::AGORA,
        ]);

        $this->assertSame('faltou', DB::table('consultas')->where('id', $id)->value('estado'));
    }

    public function test_falta_continua_a_ocupar_a_vaga(): void
    {
        $id = $this->consulta($this->pacientes[0], 'confirmada');
        DB::table('consultas')->where('id', $id)->update(['estado' => 'faltou']);

        $this->assertSame(1, (int) DB::table('consultas')->where('id', $id)->value('vaga_ocupada'));

        $this->expectException(QueryException::class);
        $this->consulta($this->pacientes[1], 'pedida');
    }

    public function test_enum_ocupa_vaga_bate_certo_com_a_coluna_gerada(): void
    {
        foreach (EstadoConsulta::cases() as $estado) {
            $id = $this->consulta($this->pacientes[0], $estado->value);
            $naBd = DB::table('consultas')->where('id', $id)->value('vaga_ocupada');

            $this->assertSame(
                $estado->ocupaVaga(),
                (int) $naBd === 1,
                "Divergência no estado {$estado->value}: enum diz ".var_export($estado->ocupaVaga(), true).', BD diz '.var_export($naBd, true)
            );

            DB::table('consultas')->where('id', $id)->delete();
        }
    }
}
