<?php

namespace Tests\Feature;

use App\Models\Consulta;
use App\Models\Especialidade;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\User;
use App\Notifications\ConsultaAtualizada;
use App\Services\Marcacoes;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificacoesTest extends TestCase
{
    use RefreshDatabase;

    private const VAGA = '2026-11-23 09:30:00';

    private Especialidade $cardiologia;

    private Profissional $medica;

    private Paciente $paciente;

    private User $rececionista;

    protected function setUp(): void
    {
        parent::setUp();

        // Quarta-feira, 18 nov. 2026. A médica atende às segundas, das 09:00 às 11:00.
        $this->travelTo(CarbonImmutable::parse('2026-11-18 08:00:00'));

        $this->cardiologia = Especialidade::factory()->create();
        $this->medica = Profissional::factory()->comEspecialidade($this->cardiologia)->comHorario(1, '09:00', '11:00')->create();
        $this->paciente = Paciente::factory()->create();
        $this->rececionista = User::factory()->rececionista()->create();
    }

    private function consulta(string $estado, ?Paciente $paciente = null): Consulta
    {
        return Consulta::factory()->for($paciente ?? $this->paciente)->for($this->medica)->{$estado}()->create(['inicio' => self::VAGA]);
    }

    /** Confirma que $user recebeu exatamente um aviso deste evento, pela aplicação e por email. */
    private function assertAvisado(User $user, string $evento, ?string $motivo = null, bool $paraProfissional = false): void
    {
        Notification::assertSentToTimes($user, ConsultaAtualizada::class, 1);
        Notification::assertSentTo($user, ConsultaAtualizada::class, fn (ConsultaAtualizada $aviso, array $canais) => $aviso->evento === $evento
            && $aviso->motivo === $motivo
            && $aviso->paraProfissional === $paraProfissional
            && $canais === ['database', 'mail']);
    }

    public function test_confirmar_um_pedido_avisa_o_paciente(): void
    {
        Notification::fake();
        $pedido = $this->consulta('pedida');

        $this->actingAs($this->rececionista)->post(route('rececao.pedidos.confirmar', $pedido));

        $this->assertAvisado($this->paciente->user, ConsultaAtualizada::CONFIRMADA);
        Notification::assertNotSentTo($this->medica->user, ConsultaAtualizada::class);
    }

    public function test_rejeitar_avisa_o_paciente_com_o_motivo(): void
    {
        Notification::fake();
        $pedido = $this->consulta('pedida');

        $this->actingAs($this->rececionista)->post(route('rececao.pedidos.rejeitar', $pedido), ['motivo' => 'Sem vaga real']);

        $this->assertAvisado($this->paciente->user, ConsultaAtualizada::REJEITADA, 'Sem vaga real');
    }

    public function test_marcar_pela_rececao_avisa_o_paciente_da_consulta_confirmada(): void
    {
        Notification::fake();

        $this->actingAs($this->rececionista)->post(route('rececao.consultas.store'), [
            'paciente_id' => $this->paciente->id, 'especialidade_id' => $this->cardiologia->id,
            'profissional_id' => $this->medica->id, 'inicio' => '2026-11-23T09:30',
        ])->assertSessionHasNoErrors();

        $this->assertAvisado($this->paciente->user, ConsultaAtualizada::CONFIRMADA);
    }

    public function test_quem_cancela_nao_recebe_o_seu_proprio_aviso(): void
    {
        Notification::fake();
        $consulta = $this->consulta('confirmada');

        // O paciente cancela: avisa-se o profissional, não o paciente.
        $this->actingAs($this->paciente->user)->post(route('paciente.consultas.cancelar', $consulta), ['motivo' => 'Viagem']);

        Notification::assertNotSentTo($this->paciente->user, ConsultaAtualizada::class);
        $this->assertAvisado($this->medica->user, ConsultaAtualizada::CANCELADA, 'Viagem', paraProfissional: true);
    }

    public function test_profissional_cancela_e_so_o_paciente_e_avisado(): void
    {
        Notification::fake();
        $consulta = $this->consulta('confirmada');

        $this->actingAs($this->medica->user)->post(route('profissional.consultas.cancelar', $consulta), ['motivo' => 'Doença']);

        $this->assertAvisado($this->paciente->user, ConsultaAtualizada::CANCELADA, 'Doença');
        Notification::assertNotSentTo($this->medica->user, ConsultaAtualizada::class);
    }

    public function test_rececao_cancela_e_sao_avisados_o_paciente_e_o_profissional(): void
    {
        Notification::fake();
        $consulta = $this->consulta('confirmada');

        $this->actingAs($this->rececionista)->post(route('rececao.consultas.cancelar', $consulta), ['motivo' => 'Pedido por telefone']);

        $this->assertAvisado($this->paciente->user, ConsultaAtualizada::CANCELADA, 'Pedido por telefone');
        $this->assertAvisado($this->medica->user, ConsultaAtualizada::CANCELADA, 'Pedido por telefone', paraProfissional: true);
        Notification::assertNotSentTo($this->rececionista, ConsultaAtualizada::class);
    }

    public function test_cancelar_um_pedido_pendente_nao_avisa_o_profissional(): void
    {
        Notification::fake();
        $pedido = $this->consulta('pedida');

        $this->actingAs($this->rececionista)->post(route('rececao.consultas.cancelar', $pedido), ['motivo' => 'Engano']);

        $this->assertAvisado($this->paciente->user, ConsultaAtualizada::CANCELADA, 'Engano');
        Notification::assertNotSentTo($this->medica->user, ConsultaAtualizada::class);
    }

    public function test_promocao_da_lista_de_espera_avisa_o_paciente_promovido(): void
    {
        Notification::fake();
        $ocupante = $this->consulta('confirmada', Paciente::factory()->create());
        $emEspera = $this->consulta('emEspera');

        $this->actingAs($this->rececionista)->post(route('rececao.consultas.cancelar', $ocupante), ['motivo' => 'Teste']);

        Notification::assertSentTo($this->paciente->user, ConsultaAtualizada::class, fn (ConsultaAtualizada $aviso) => $aviso->evento === ConsultaAtualizada::PROMOVIDA && $aviso->consulta->is($emEspera));
        Notification::assertSentTo($ocupante->paciente->user, ConsultaAtualizada::class, fn (ConsultaAtualizada $aviso) => $aviso->evento === ConsultaAtualizada::CANCELADA);
    }

    public function test_reagendar_avisa_o_paciente_da_data_nova_e_o_profissional_que_perde_a_consulta(): void
    {
        Notification::fake();
        $consulta = $this->consulta('confirmada');

        $this->actingAs($this->rececionista)->post(route('rececao.consultas.reagendar', $consulta), [
            'profissional_id' => $this->medica->id, 'inicio' => '2026-11-23T10:30',
        ])->assertSessionHasNoErrors();

        Notification::assertSentToTimes($this->paciente->user, ConsultaAtualizada::class, 1);
        Notification::assertSentTo($this->paciente->user, ConsultaAtualizada::class, fn (ConsultaAtualizada $aviso) => $aviso->evento === ConsultaAtualizada::REAGENDADA
            && $aviso->consulta->inicio->toDateTimeString() === '2026-11-23 10:30:00');
        $this->assertAvisado($this->medica->user, ConsultaAtualizada::REAGENDADA, paraProfissional: true);
    }

    public function test_reagendamento_recusado_nao_avisa_ninguem(): void
    {
        Notification::fake();
        $consulta = $this->consulta('confirmada');
        // O paciente já tem outra consulta à hora nova: a base de dados recusa e a transação é desfeita.
        $colega = Profissional::factory()->comEspecialidade($this->cardiologia)->create();
        Consulta::factory()->confirmada()->for($this->paciente)->for($colega)->create(['inicio' => '2026-11-23 10:30:00']);

        $this->actingAs($this->rececionista)->post(route('rececao.consultas.reagendar', $consulta), [
            'profissional_id' => $this->medica->id, 'inicio' => '2026-11-23T10:30',
        ])->assertSessionHasErrors('inicio');

        Notification::assertNothingSent();
    }

    public function test_paciente_sem_conta_recebe_so_o_email_no_email_de_contacto(): void
    {
        Notification::fake();
        $comEmail = Paciente::factory()->semConta()->create(['email_contacto' => 'balcao@exemplo.pt']);
        $semEmail = Paciente::factory()->semConta()->create();
        $marcacoes = app(Marcacoes::class);

        $marcacoes->confirmar($this->consulta('pedida', $comEmail), $this->rececionista);
        $marcacoes->confirmar(Consulta::factory()->pedida()->for($semEmail)->for($this->medica)->create(['inicio' => '2026-11-23 10:00:00']), $this->rececionista);

        Notification::assertSentOnDemandTimes(ConsultaAtualizada::class, 1);
        Notification::assertSentOnDemand(ConsultaAtualizada::class, fn (ConsultaAtualizada $aviso, array $canais, AnonymousNotifiable $destino) => $canais === ['mail'] && $destino->routes['mail'] === 'balcao@exemplo.pt');
    }

    public function test_aviso_fica_gravado_e_o_email_tem_a_frase_e_o_motivo(): void
    {
        $consulta = $this->consulta('confirmada');

        $this->actingAs($this->medica->user)->post(route('profissional.consultas.cancelar', $consulta), ['motivo' => 'Doença']);

        $gravada = $this->paciente->user->notifications()->sole();
        $this->assertSame(ConsultaAtualizada::CANCELADA, $gravada->data['evento']);
        $this->assertSame('Doença', $gravada->data['motivo']);
        $this->assertNull($gravada->read_at);

        $email = (new ConsultaAtualizada($consulta, ConsultaAtualizada::CANCELADA, 'Doença'))->toMail($this->paciente->user)->render();
        $this->assertStringContainsString('23/11/2026 09:30', (string) $email);
        $this->assertStringContainsString('foi cancelada', (string) $email);
        $this->assertStringContainsString('Doença', (string) $email);
    }

    public function test_pagina_mostra_as_notificacoes_e_marca_as_como_lidas(): void
    {
        $consulta = $this->consulta('pedida');
        app(Marcacoes::class)->rejeitar($consulta, $this->rececionista, 'Sem vaga real');
        $conta = $this->paciente->user;

        $this->actingAs($conta)->get(route('home'))->assertSee('data-por-ler', false);

        $this->actingAs($conta)->get(route('notificacoes.index'))
            ->assertOk()
            ->assertSee('Pedido de consulta rejeitado')
            ->assertSee('23/11/2026 09:30')
            ->assertSee('Sem vaga real')
            ->assertSee('Nova');

        $this->assertSame(0, $conta->unreadNotifications()->count());
        $this->actingAs($conta)->get(route('notificacoes.index'))->assertDontSee('data-por-ler', false);
    }

    public function test_cada_conta_so_ve_as_suas_notificacoes(): void
    {
        app(Marcacoes::class)->rejeitar($this->consulta('pedida'), $this->rececionista, 'Motivo privado');

        $this->get(route('notificacoes.index'))->assertRedirect(route('login'));

        $this->actingAs($this->medica->user)->get(route('notificacoes.index'))
            ->assertOk()->assertSee('Não tem notificações.')->assertDontSee('Motivo privado');
    }

    public function test_pagina_em_ingles_escreve_a_frase_no_idioma_de_quem_le(): void
    {
        app(Marcacoes::class)->confirmar($this->consulta('pedida'), $this->rececionista);

        $this->actingAs($this->paciente->user)->withSession(['locale' => 'en'])
            ->get(route('notificacoes.index'))
            ->assertSee('Notifications')->assertSee('Appointment confirmed')->assertSee('is confirmed');
    }
}
