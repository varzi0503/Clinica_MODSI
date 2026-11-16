<?php

namespace Tests\Feature;

use App\Models\Consulta;
use App\Models\Documento;
use App\Models\Observacao;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ObservacoesDocumentosTest extends TestCase
{
    use RefreshDatabase;

    private Paciente $paciente;

    private Profissional $medica;

    private Consulta $consulta;

    private User $rececionista;

    private User $outroPaciente;

    private User $outroProfissional;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Documento::DISCO);

        // Quarta-feira, 18 nov. 2026, 10:00.
        $this->travelTo(CarbonImmutable::parse('2026-11-18 10:00:00'));

        $this->paciente = Paciente::factory()->create();
        $this->medica = Profissional::factory()->comEspecialidade()->create();
        $this->consulta = Consulta::factory()->confirmada()->for($this->paciente)->for($this->medica)->create(['inicio' => '2026-11-20 10:00:00']);
        $this->rececionista = User::factory()->rececionista()->create();
        $this->outroPaciente = Paciente::factory()->create()->user;
        $this->outroProfissional = Profissional::factory()->create()->user;
    }

    /** O disco simulado dos documentos, com o tipo concreto que tem assertExists() e assertMissing(). */
    private function disco(): FilesystemAdapter
    {
        return Storage::disk(Documento::DISCO);
    }

    private function pdf(string $nome = 'analises.pdf', int $kb = 200): UploadedFile
    {
        return UploadedFile::fake()->create($nome, $kb, 'application/pdf');
    }

    private function carregar(User $user, ?UploadedFile $ficheiro = null, ?Consulta $consulta = null): TestResponse
    {
        return $this->actingAs($user)->post(route('documentos.store', $consulta ?? $this->consulta), ['ficheiro' => $ficheiro ?? $this->pdf()]);
    }

    public function test_profissional_escreve_observacoes_e_so_ele_as_ve(): void
    {
        $this->actingAs($this->medica->user)
            ->post(route('profissional.consultas.observar', $this->consulta), ['texto' => 'Tensão arterial elevada.'])
            ->assertSessionHas('success');

        $observacao = $this->consulta->observacoes()->sole();
        $this->assertSame('Tensão arterial elevada.', $observacao->texto);
        $this->assertSame($this->medica->user_id, $observacao->user_id);

        $this->actingAs($this->medica->user)->get(route('profissional.consultas.show', $this->consulta))
            ->assertOk()->assertSee('Tensão arterial elevada.')->assertSee($this->paciente->nome);

        // O paciente abre a sua consulta e não vê as observações.
        $this->actingAs($this->paciente->user)->get(route('paciente.consultas.show', $this->consulta))
            ->assertOk()->assertDontSee('Tensão arterial elevada.')->assertDontSee('Observações');
    }

    public function test_observacao_vazia_ou_em_consulta_que_nao_as_aceita_e_recusada(): void
    {
        $this->actingAs($this->medica->user)
            ->post(route('profissional.consultas.observar', $this->consulta), ['texto' => ''])
            ->assertSessionHasErrors('texto');

        $pedida = Consulta::factory()->pedida()->for($this->medica)->create(['inicio' => '2026-11-20 10:30:00']);
        $this->actingAs($this->medica->user)
            ->post(route('profissional.consultas.observar', $pedida), ['texto' => 'Cedo demais'])
            ->assertSessionHas('error');

        $this->assertSame(0, Observacao::count());
    }

    public function test_so_o_profissional_da_consulta_abre_a_ficha_e_escreve_observacoes(): void
    {
        foreach ([$this->outroProfissional, $this->rececionista, $this->paciente->user, User::factory()->administrador()->create()] as $user) {
            $this->actingAs($user)->get(route('profissional.consultas.show', $this->consulta))->assertForbidden();
            $this->actingAs($user)->post(route('profissional.consultas.observar', $this->consulta), ['texto' => 'Intruso'])->assertForbidden();
        }

        $this->assertSame(0, Observacao::count());
    }

    public function test_paciente_e_profissional_carregam_documentos_e_cada_um_ve_os_do_outro(): void
    {
        $this->carregar($this->paciente->user, $this->pdf('analises.pdf'))->assertSessionHas('success');
        $this->carregar($this->medica->user, UploadedFile::fake()->create('receita.png', 50, 'image/png'))->assertSessionHas('success');

        $documentos = $this->consulta->documentos()->get()->keyBy('nome_original');
        $this->assertSame($this->paciente->user_id, $documentos['analises.pdf']->user_id);
        $this->assertSame($this->medica->user_id, $documentos['receita.png']->user_id);
        $this->assertSame('application/pdf', $documentos['analises.pdf']->mime);

        // O nome no disco é gerado: o nome enviado pelo utilizador nunca chega ao sistema de ficheiros.
        foreach ($documentos as $documento) {
            $this->disco()->assertExists($documento->caminho);
            $this->assertStringStartsWith("documentos/{$this->consulta->id}/", $documento->caminho);
            $this->assertStringNotContainsString(pathinfo($documento->nome_original, PATHINFO_FILENAME), $documento->caminho);
        }

        $this->actingAs($this->paciente->user)->get(route('paciente.consultas.show', $this->consulta))
            ->assertOk()->assertSee('analises.pdf')->assertSee('receita.png');
        $this->actingAs($this->medica->user)->get(route('profissional.consultas.show', $this->consulta))
            ->assertOk()->assertSee('analises.pdf')->assertSee('receita.png');
    }

    public function test_descarregar_devolve_o_ficheiro_com_o_nome_original(): void
    {
        $this->carregar($this->paciente->user, $this->pdf('analises de sangue.pdf'));
        $documento = Documento::sole();

        foreach ([$this->paciente->user, $this->medica->user] as $user) {
            $this->actingAs($user)->get(route('documentos.show', $documento))
                ->assertOk()
                ->assertDownload('analises de sangue.pdf');
        }
    }

    public function test_recusa_tipos_nao_permitidos_ficheiros_grandes_e_pedidos_sem_ficheiro(): void
    {
        $recusados = [
            UploadedFile::fake()->create('script.php', 10, 'text/x-php'),
            UploadedFile::fake()->create('folha.xlsx', 10),
            UploadedFile::fake()->create('disfarce.pdf', 10, 'text/html'),
            $this->pdf('enorme.pdf', Documento::TAMANHO_MAXIMO_KB + 1),
        ];

        foreach ($recusados as $ficheiro) {
            $this->carregar($this->paciente->user, $ficheiro)->assertSessionHasErrors('ficheiro');
        }
        $this->actingAs($this->paciente->user)->post(route('documentos.store', $this->consulta), [])->assertSessionHasErrors('ficheiro');

        $this->assertSame(0, Documento::count());
        $this->assertSame([], $this->disco()->allFiles());

        $this->carregar($this->paciente->user, $this->pdf('no-limite.pdf', Documento::TAMANHO_MAXIMO_KB))->assertSessionHasNoErrors();
    }

    public function test_quem_nao_e_da_consulta_nao_carrega_nao_descarrega_nem_apaga(): void
    {
        $documento = Documento::factory()->doPaciente()->for($this->consulta)->create();
        $this->disco()->put($documento->caminho, 'conteúdo');

        $this->get(route('documentos.show', $documento))->assertRedirect(route('login'));

        foreach ([$this->outroPaciente, $this->outroProfissional, $this->rececionista, User::factory()->administrador()->create()] as $user) {
            $this->carregar($user)->assertForbidden();
            $this->actingAs($user)->get(route('documentos.show', $documento))->assertForbidden();
            $this->actingAs($user)->delete(route('documentos.destroy', $documento))->assertForbidden();
        }
        $this->actingAs($this->outroPaciente)->get(route('paciente.consultas.show', $this->consulta))->assertForbidden();

        $this->assertSame(1, Documento::count());
        $this->disco()->assertExists($documento->caminho);
    }

    public function test_so_quem_carregou_apaga_e_o_ficheiro_sai_do_disco(): void
    {
        $this->carregar($this->paciente->user);
        $documento = Documento::sole();

        // O profissional vê o documento do paciente, mas não o apaga.
        $this->actingAs($this->medica->user)->delete(route('documentos.destroy', $documento))->assertForbidden();
        $this->assertModelExists($documento);

        $this->actingAs($this->paciente->user)->delete(route('documentos.destroy', $documento))->assertSessionHas('success');
        $this->assertModelMissing($documento);
        $this->disco()->assertMissing($documento->caminho);
    }

    public function test_nao_se_juntam_documentos_a_consultas_canceladas_ou_em_espera(): void
    {
        foreach (['cancelada', 'emEspera', 'rejeitada'] as $i => $estado) {
            $consulta = Consulta::factory()->for($this->paciente)->for($this->medica)->{$estado}()->create(['inicio' => "2026-11-2{$i} 11:00:00"]);

            $this->carregar($this->paciente->user, consulta: $consulta)->assertSessionHas('error');
        }

        $this->assertSame(0, Documento::count());
        $this->assertSame([], $this->disco()->allFiles());
    }

    public function test_paciente_junta_documentos_a_um_pedido_ainda_por_confirmar_e_a_uma_consulta_realizada(): void
    {
        $pedida = Consulta::factory()->pedida()->for($this->paciente)->for($this->medica)->create(['inicio' => '2026-11-23 10:00:00']);
        $realizada = Consulta::factory()->realizada()->for($this->paciente)->for($this->medica)->create(['inicio' => '2026-11-10 10:00:00']);

        $this->carregar($this->paciente->user, consulta: $pedida)->assertSessionHas('success');
        $this->carregar($this->paciente->user, consulta: $realizada)->assertSessionHas('success');

        $this->assertSame(2, Documento::count());
    }

    public function test_as_listas_ligam_a_ficha_da_consulta_e_as_paginas_existem_em_ingles(): void
    {
        $this->actingAs($this->paciente->user)->get(route('paciente.consultas.index'))
            ->assertSee(route('paciente.consultas.show', $this->consulta));
        $this->actingAs($this->medica->user)->get(route('profissional.agenda.index'))
            ->assertSee(route('profissional.consultas.show', $this->consulta));

        $this->actingAs($this->medica->user)->withSession(['locale' => 'en'])
            ->get(route('profissional.consultas.show', $this->consulta))
            ->assertSee('Notes')->assertSee('Documents')->assertSee('Upload document');
    }

    public function test_factories_criam_observacoes_e_documentos_coerentes(): void
    {
        $observacao = Observacao::factory()->create();
        $doProfissional = Documento::factory()->create();
        $doPaciente = Documento::factory()->doPaciente()->create();

        $this->assertSame($observacao->consulta->profissional->user_id, $observacao->user_id);
        $this->assertSame($doProfissional->consulta->profissional->user_id, $doProfissional->user_id);
        $this->assertSame($doPaciente->consulta->paciente->user_id, $doPaciente->user_id);
        $this->assertMatchesRegularExpression('/^\d+ KB$|^\d+,\d MB$/', $doProfissional->tamanhoLegivel());
    }
}
