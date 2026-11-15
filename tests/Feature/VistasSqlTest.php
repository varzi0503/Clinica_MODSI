<?php

namespace Tests\Feature;

use App\Enums\EstadoConsulta;
use App\Models\Consulta;
use App\Models\Especialidade;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VistasSqlTest extends TestCase
{
    use RefreshDatabase;

    private Especialidade $cardiologia;

    private Especialidade $pediatria;

    private Profissional $marta;

    private Profissional $eduardo;

    protected function setUp(): void
    {
        parent::setUp();

        // Quarta-feira, 18 nov. 2026, 11:00.
        $this->travelTo(CarbonImmutable::parse('2026-11-18 11:00:00'));

        $this->cardiologia = Especialidade::factory()->create(['nome' => 'Cardiologia']);
        $this->pediatria = Especialidade::factory()->create(['nome' => 'Pediatria']);

        $this->marta = Profissional::factory()->medico()
            ->for(User::factory()->profissional()->state(['name' => 'Marta Médica']))
            ->comEspecialidade($this->cardiologia)->comEspecialidade($this->pediatria)->create();
        $this->eduardo = Profissional::factory()->enfermeiro()
            ->for(User::factory()->profissional()->state(['name' => 'Eduardo Enfermeiro']))
            ->comEspecialidade($this->pediatria)->create();
    }

    public function test_detalhe_junta_a_consulta_aos_nomes(): void
    {
        $paciente = Paciente::factory()->semConta()->create(['nome' => 'Paulo Paciente', 'num_utente' => '123456789']);
        $consulta = Consulta::factory()->confirmada()->for($paciente)->for($this->marta)->create([
            'especialidade_id' => $this->pediatria->id,
            'inicio' => '2026-11-20 10:00:00',
            'motivo_consulta' => 'Febre',
        ]);

        $linha = DB::table('v_consultas_detalhe')->where('consulta_id', $consulta->id)->sole();

        $this->assertSame('2026-11-20 10:00:00', $linha->inicio);
        $this->assertSame('confirmada', $linha->estado);
        $this->assertEquals(1, $linha->vaga_ocupada);
        $this->assertSame('Febre', $linha->motivo_consulta);
        $this->assertNull($linha->reagendada_de_id);
        $this->assertSame('Paulo Paciente', $linha->paciente_nome);
        $this->assertSame('123456789', $linha->paciente_num_utente);
        $this->assertSame('Marta Médica', $linha->profissional_nome);
        $this->assertSame('medico', $linha->profissional_tipo);
        $this->assertSame('Pediatria', $linha->especialidade_nome);
    }

    public function test_detalhe_serve_de_agenda_de_um_profissional(): void
    {
        Consulta::factory()->confirmada()->for($this->marta)->count(2)->create();
        Consulta::factory()->confirmada()->for($this->eduardo)->create();

        $this->assertSame(3, DB::table('v_consultas_detalhe')->count());
        $this->assertSame(2, DB::table('v_consultas_detalhe')->where('profissional_id', $this->marta->id)->count());
    }

    public function test_estatisticas_por_estado_contam_cada_estado(): void
    {
        Consulta::factory()->confirmada()->for($this->marta)->count(3)->create();
        Consulta::factory()->realizada()->for($this->marta)->count(2)->create();
        Consulta::factory()->emEspera()->for($this->eduardo)->create();

        $totais = DB::table('v_estatisticas_estado')->pluck('total', 'estado')->map(fn ($total) => (int) $total)->all();

        // Estados sem consultas não aparecem na vista.
        $this->assertEquals(['confirmada' => 3, 'realizada' => 2, 'em_espera' => 1], $totais);
    }

    public function test_estatisticas_por_profissional_incluem_quem_nao_tem_consultas(): void
    {
        Consulta::factory()->confirmada()->for($this->marta)->count(2)->create();
        Consulta::factory()->faltou()->for($this->marta)->create();
        Consulta::factory()->rejeitada()->for($this->marta)->create();

        $linhas = DB::table('v_estatisticas_profissional')->get()->keyBy('profissional_id');

        $marta = $linhas[$this->marta->id];
        $this->assertSame('Marta Médica', $marta->profissional_nome);
        $this->assertSame('medico', $marta->profissional_tipo);
        $this->assertEquals(4, $marta->total);
        $this->assertEquals(2, $marta->confirmadas);
        $this->assertEquals(1, $marta->faltas);
        $this->assertEquals(1, $marta->rejeitadas);
        $this->assertEquals(0, $marta->realizadas);

        $eduardo = $linhas[$this->eduardo->id];
        $this->assertEquals(0, $eduardo->total);
        $this->assertEquals(0, $eduardo->confirmadas);
    }

    public function test_estatisticas_por_especialidade_incluem_as_que_nao_tem_consultas(): void
    {
        Especialidade::factory()->create(['nome' => 'Dermatologia']);
        Consulta::factory()->pedida()->for($this->marta)->create(['especialidade_id' => $this->cardiologia->id]);
        Consulta::factory()->cancelada()->for($this->marta)->create(['especialidade_id' => $this->pediatria->id]);
        Consulta::factory()->realizada()->for($this->eduardo)->create();

        $linhas = DB::table('v_estatisticas_especialidade')->get()->keyBy('especialidade_nome');

        $this->assertEquals(1, $linhas['Cardiologia']->total);
        $this->assertEquals(1, $linhas['Cardiologia']->pedidas);
        $this->assertEquals(2, $linhas['Pediatria']->total);
        $this->assertEquals(1, $linhas['Pediatria']->canceladas);
        $this->assertEquals(1, $linhas['Pediatria']->realizadas);
        $this->assertEquals(0, $linhas['Dermatologia']->total);
    }

    public function test_as_contagens_por_estado_das_vistas_cobrem_todos_os_estados(): void
    {
        foreach (EstadoConsulta::cases() as $estado) {
            Consulta::factory()->for($this->marta)->state(['estado' => $estado])->create();
        }

        $marta = (array) DB::table('v_estatisticas_profissional')->where('profissional_id', $this->marta->id)->sole();
        $porEstado = array_sum(array_map('intval', array_diff_key($marta, array_flip(['profissional_id', 'profissional_nome', 'profissional_tipo', 'total']))));

        // Se entrar um estado novo no enum e a vista não o contar, a soma deixa de bater com o total.
        $this->assertSame(count(EstadoConsulta::cases()), (int) $marta['total']);
        $this->assertSame((int) $marta['total'], $porEstado);
    }

    public function test_consulta_reagendada_aponta_para_a_antiga(): void
    {
        $antiga = Consulta::factory()->cancelada()->for($this->marta)->create();
        $nova = Consulta::factory()->confirmada()->for($this->marta)->create();
        $nova->forceFill(['reagendada_de_id' => $antiga->id])->save();

        $this->assertTrue($nova->reagendadaDe->is($antiga));
        $this->assertTrue($antiga->reagendamento->is($nova));
        $this->assertSame($antiga->id, DB::table('v_consultas_detalhe')->where('consulta_id', $nova->id)->value('reagendada_de_id'));
    }

    public function test_reagendada_de_tem_de_ser_uma_consulta_que_existe(): void
    {
        $consulta = Consulta::factory()->confirmada()->for($this->marta)->create();

        $this->expectException(QueryException::class);

        $consulta->forceFill(['reagendada_de_id' => 999999])->save();
    }
}
