<?php

namespace Tests\Feature;

use App\Models\Consulta;
use App\Models\Horario;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoliciesTest extends TestCase
{
    use RefreshDatabase;

    private Consulta $consulta;

    private User $rececionista;

    private User $admin;

    private User $outroPaciente;

    private User $outroProfissional;

    protected function setUp(): void
    {
        parent::setUp();

        $this->consulta = Consulta::factory()->confirmada()->create();
        $this->rececionista = User::factory()->rececionista()->create();
        $this->admin = User::factory()->administrador()->create();
        $this->outroPaciente = Paciente::factory()->create()->user;
        $this->outroProfissional = Profissional::factory()->create()->user;
    }

    public function test_ver_e_cancelar_sao_da_rececao_e_dos_donos_da_consulta(): void
    {
        $dono = $this->consulta->paciente->user;
        $medico = $this->consulta->profissional->user;

        foreach (['ver', 'cancelar'] as $acao) {
            $this->assertTrue($this->rececionista->can($acao, $this->consulta), $acao);
            $this->assertTrue($dono->can($acao, $this->consulta), $acao);
            $this->assertTrue($medico->can($acao, $this->consulta), $acao);

            $this->assertFalse($this->outroPaciente->can($acao, $this->consulta), $acao);
            $this->assertFalse($this->outroProfissional->can($acao, $this->consulta), $acao);
            $this->assertFalse($this->admin->can($acao, $this->consulta), $acao);
        }
    }

    public function test_registar_e_so_do_profissional_da_consulta(): void
    {
        $this->assertTrue($this->consulta->profissional->user->can('registar', $this->consulta));

        $this->assertFalse($this->outroProfissional->can('registar', $this->consulta));
        $this->assertFalse($this->rececionista->can('registar', $this->consulta));
        $this->assertFalse($this->consulta->paciente->user->can('registar', $this->consulta));
    }

    public function test_confirmar_e_rejeitar_sao_so_da_rececao(): void
    {
        foreach (['confirmar', 'rejeitar'] as $acao) {
            $this->assertTrue($this->rececionista->can($acao, $this->consulta), $acao);

            $this->assertFalse($this->admin->can($acao, $this->consulta), $acao);
            $this->assertFalse($this->consulta->profissional->user->can($acao, $this->consulta), $acao);
            $this->assertFalse($this->consulta->paciente->user->can($acao, $this->consulta), $acao);
        }
    }

    public function test_conta_de_paciente_sem_ficha_nao_ve_consultas_de_fichas_de_balcao(): void
    {
        // Uma ficha de balcão tem user_id nulo; uma conta sem ficha não pode "coincidir" com ela.
        $deBalcao = Consulta::factory()->for(Paciente::factory()->semConta())->create();
        $semFicha = User::factory()->paciente()->create();

        $this->assertFalse($semFicha->can('ver', $deBalcao));
        $this->assertFalse($semFicha->can('cancelar', $deBalcao));
    }

    public function test_so_o_proprio_profissional_apaga_um_periodo_do_seu_horario(): void
    {
        $horario = Horario::factory()->create();

        $this->assertTrue($horario->profissional->user->can('apagar', $horario));

        $this->assertFalse($this->outroProfissional->can('apagar', $horario));
        $this->assertFalse($this->rececionista->can('apagar', $horario));
    }
}
