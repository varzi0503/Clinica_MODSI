<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regras de integridade garantidas pela própria base de dados.
 * Corre em SQLite: as restrições CHECK que só existem no MySQL (horários e vagas de 30 min) não são testadas aqui.
 */
class EsquemaConsultasTest extends TestCase
{
    use RefreshDatabase;

    private const AGORA = '2026-11-10 09:00:00';

    private const VAGA = '2026-11-12 10:00:00';

    /** @var array<string, int> */
    private array $id = [];

    protected function setUp(): void
    {
        parent::setUp();

        $perfil = DB::table('perfis')->insertGetId(['nome' => 'profissional']);
        $userMedica = DB::table('users')->insertGetId(['perfil_id' => $perfil, 'name' => 'Médica', 'email' => 'medica@teste.pt', 'password' => 'x']);
        $userEnfermeira = DB::table('users')->insertGetId(['perfil_id' => $perfil, 'name' => 'Enfermeira', 'email' => 'enfermeira@teste.pt', 'password' => 'x']);

        $this->id = [
            'perfil' => $perfil,
            'medica' => DB::table('profissionais')->insertGetId(['user_id' => $userMedica, 'tipo' => 'medico', 'num_cedula' => '123']),
            'enfermeira' => DB::table('profissionais')->insertGetId(['user_id' => $userEnfermeira, 'tipo' => 'enfermeiro', 'num_cedula' => '123']),
            'cardiologia' => DB::table('especialidades')->insertGetId(['nome' => 'Cardiologia']),
            'enfermagem' => DB::table('especialidades')->insertGetId(['nome' => 'Enfermagem']),
            'p1' => DB::table('pacientes')->insertGetId(['nome' => 'Paciente 1', 'num_utente' => '111111111']),
            'p2' => DB::table('pacientes')->insertGetId(['nome' => 'Paciente 2', 'num_utente' => '222222222']),
            'p3' => DB::table('pacientes')->insertGetId(['nome' => 'Paciente 3', 'num_utente' => '333333333']),
        ];

        DB::table('especialidade_profissional')->insert([
            ['profissional_id' => $this->id['medica'], 'especialidade_id' => $this->id['cardiologia']],
            ['profissional_id' => $this->id['enfermeira'], 'especialidade_id' => $this->id['enfermagem']],
        ]);
    }

    private function consulta(string $paciente, string $profissional, string $especialidade, string $estado): int
    {
        return DB::table('consultas')->insertGetId([
            'paciente_id' => $this->id[$paciente],
            'profissional_id' => $this->id[$profissional],
            'especialidade_id' => $this->id[$especialidade],
            'inicio' => self::VAGA,
            'estado' => $estado,
            'data_pedido' => self::AGORA,
            'data_estado' => self::AGORA,
        ]);
    }

    public function test_utilizador_sem_perfil_e_rejeitado(): void
    {
        $this->expectException(QueryException::class);
        DB::table('users')->insert(['name' => 'Sem perfil', 'email' => 'x@teste.pt', 'password' => 'x']);
    }

    public function test_cedula_repetida_so_e_rejeitada_dentro_da_mesma_ordem(): void
    {
        // O setUp já criou a cédula 123 para um médico e para um enfermeiro.
        $user = DB::table('users')->insertGetId(['perfil_id' => $this->id['perfil'], 'name' => 'Outro', 'email' => 'outro@teste.pt', 'password' => 'x']);

        $this->expectException(QueryException::class);
        DB::table('profissionais')->insert(['user_id' => $user, 'tipo' => 'medico', 'num_cedula' => '123']);
    }

    public function test_especialidade_tem_de_pertencer_ao_profissional(): void
    {
        $this->expectException(QueryException::class);
        $this->consulta('p1', 'enfermeira', 'cardiologia', 'pedida'); // cardiologia com a enfermeira
    }

    public function test_vaga_ocupada_nao_aceita_segundo_pedido(): void
    {
        $this->consulta('p1', 'medica', 'cardiologia', 'pedida');

        $this->expectException(QueryException::class);
        $this->consulta('p2', 'medica', 'cardiologia', 'pedida');
    }

    public function test_varios_pedidos_em_espera_na_mesma_vaga(): void
    {
        $this->consulta('p1', 'medica', 'cardiologia', 'pedida');
        $this->consulta('p2', 'medica', 'cardiologia', 'em_espera');
        $this->consulta('p3', 'medica', 'cardiologia', 'em_espera');

        $this->assertSame(3, DB::table('consultas')->count());
    }

    public function test_cancelar_liberta_a_vaga_para_um_pedido_em_espera(): void
    {
        $primeiro = $this->consulta('p1', 'medica', 'cardiologia', 'pedida');
        $emEspera = $this->consulta('p2', 'medica', 'cardiologia', 'em_espera');

        DB::table('consultas')->where('id', $primeiro)->update(['estado' => 'cancelada']);
        DB::table('consultas')->where('id', $emEspera)->update(['estado' => 'pedida']);

        $this->assertSame('pedida', DB::table('consultas')->where('id', $emEspera)->value('estado'));
    }

    public function test_paciente_nao_tem_duas_consultas_a_mesma_hora(): void
    {
        $this->consulta('p1', 'medica', 'cardiologia', 'pedida');

        $this->expectException(QueryException::class);
        $this->consulta('p1', 'enfermeira', 'enfermagem', 'pedida');
    }

    public function test_historico_aceita_mudanca_feita_pelo_sistema(): void
    {
        $id = $this->consulta('p1', 'medica', 'cardiologia', 'pedida');

        DB::table('historico_estados')->insert([
            'consulta_id' => $id,
            'user_id' => null,
            'estado' => 'pedida',
            'data_hora' => self::AGORA,
        ]);

        $this->assertSame(1, DB::table('historico_estados')->count());
    }
}
