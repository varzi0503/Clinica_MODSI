<?php

namespace Tests\Feature;

use App\Enums\EstadoConsulta;
use App\Models\Consulta;
use App\Models\Especialidade;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\User;
use App\Services\Marcacoes;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarcacoesTest extends TestCase
{
    use RefreshDatabase;

    private Marcacoes $marcacoes;

    private Especialidade $cardiologia;

    private Profissional $medica;

    private Paciente $paciente;

    private User $rececionista;

    private CarbonImmutable $vaga;

    protected function setUp(): void
    {
        parent::setUp();

        // Quarta-feira, 18 nov. 2026. A médica atende às segundas, das 09:00 às 11:00.
        $this->travelTo(CarbonImmutable::parse('2026-11-18 08:00:00'));

        $this->marcacoes = app(Marcacoes::class);
        $this->cardiologia = Especialidade::factory()->create();
        $this->medica = Profissional::factory()->comEspecialidade($this->cardiologia)->comHorario(1, '09:00', '11:00')->create();
        $this->paciente = Paciente::factory()->create();
        $this->rececionista = User::factory()->rececionista()->create();
        $this->vaga = CarbonImmutable::parse('2026-11-23 09:30:00');
    }

    /** @return array<string, array<int, string>> */
    private function erros(callable $acao): array
    {
        try {
            $acao();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->fail('Esperava uma ValidationException.');
    }

    public function test_pedir_cria_um_pedido_pendente_em_nome_de_quem_pediu(): void
    {
        $consulta = $this->marcacoes->pedir($this->paciente, $this->medica, $this->cardiologia->id, $this->vaga, 'Dores', $this->paciente->user);

        $this->assertSame(EstadoConsulta::Pedida, $consulta->estado);
        $this->assertSame('Dores', $consulta->motivo_consulta);
        $this->assertTrue($consulta->inicio->equalTo($this->vaga));
        $this->assertSame([[EstadoConsulta::Pedida, $this->paciente->user_id]], $consulta->historico->map(fn ($h) => [$h->estado, $h->user_id])->all());
    }

    public function test_marcar_pela_rececao_fica_confirmada_com_os_dois_passos_no_historico(): void
    {
        $consulta = $this->marcacoes->marcarPelaRececao($this->paciente, $this->medica, $this->cardiologia->id, $this->vaga, null, $this->rececionista);

        $this->assertSame(EstadoConsulta::Confirmada, $consulta->fresh()->estado);
        $this->assertSame(
            [[EstadoConsulta::Pedida, $this->rececionista->id], [EstadoConsulta::Confirmada, $this->rececionista->id]],
            $consulta->historico()->get()->map(fn ($h) => [$h->estado, $h->user_id])->all(),
        );
    }

    public function test_recusa_especialidade_que_o_profissional_nao_exerce(): void
    {
        $outra = Especialidade::factory()->create();

        $erros = $this->erros(fn () => $this->marcacoes->pedir($this->paciente, $this->medica, $outra->id, $this->vaga, null, $this->paciente->user));

        $this->assertArrayHasKey('especialidade_id', $erros);
        $this->assertSame(0, Consulta::count());
    }

    public function test_recusa_vaga_fora_do_horario_passada_ou_ja_ocupada(): void
    {
        Consulta::factory()->confirmada()->for($this->medica)->create(['inicio' => '2026-11-23 10:00:00']);

        $indisponiveis = [
            'fora do horário' => '2026-11-23 14:00:00',
            'dia sem atendimento' => '2026-11-24 09:30:00',
            'já passou' => '2026-11-16 09:30:00',
            'ocupada' => '2026-11-23 10:00:00',
        ];

        foreach ($indisponiveis as $caso => $inicio) {
            $erros = $this->erros(fn () => $this->marcacoes->marcarPelaRececao(
                $this->paciente, $this->medica, $this->cardiologia->id, CarbonImmutable::parse($inicio), null, $this->rececionista,
            ));

            $this->assertArrayHasKey('inicio', $erros, $caso);
        }

        $this->assertSame(1, Consulta::count());
    }

    public function test_paciente_com_consulta_a_essa_hora_noutro_profissional_e_recusado_sem_gravar_nada(): void
    {
        $outro = Profissional::factory()->comEspecialidade($this->cardiologia)->comHorario(1, '09:00', '11:00')->create();
        Consulta::factory()->confirmada()->for($this->paciente)->for($outro)->create(['inicio' => $this->vaga]);

        $erros = $this->erros(fn () => $this->marcacoes->marcarPelaRececao(
            $this->paciente, $this->medica, $this->cardiologia->id, $this->vaga, null, $this->rececionista,
        ));

        $this->assertArrayHasKey('inicio', $erros);
        // A transação desfaz o pedido: não fica uma consulta "pedida" órfã nem histórico.
        $this->assertSame(1, Consulta::count());
        $this->assertSame(0, Consulta::where('profissional_id', $this->medica->id)->count());
    }
}
