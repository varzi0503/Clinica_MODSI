<?php

namespace Tests\Feature;

use App\Models\Consulta;
use App\Models\Especialidade;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Os ficheiros de resources/js procuram estes atributos nas páginas. Se um redesign os apagar,
 * o AJAX deixa de funcionar sem erro visível (os formulários continuam a funcionar sem JS), por isso
 * os outros testes não dariam pela falta (D42).
 */
class GanchosJavascriptTest extends TestCase
{
    use RefreshDatabase;

    private const DISPONIBILIDADE = ['data-pagina="disponibilidade"', 'data-url=', 'data-locale=', 'data-filtro', 'data-resultados', 'data-texto-erro=', 'data-texto-sem-vagas='];

    private const MARCACAO = ['data-selecionavel="true"', 'data-marcacao', 'data-escolha', 'data-aviso-espera', 'data-texto-espera=', 'data-texto-legenda='];

    private User $rececionista;

    private Profissional $medica;

    protected function setUp(): void
    {
        parent::setUp();

        // Quarta-feira, 18 nov. 2026. A médica atende às segundas, das 09:00 às 11:00.
        $this->travelTo(CarbonImmutable::parse('2026-11-18 08:00:00'));

        $this->medica = Profissional::factory()->comEspecialidade(Especialidade::factory()->create())->comHorario(1, '09:00', '11:00')->create();
        $this->rececionista = User::factory()->rececionista()->create();
    }

    /** @param  list<string>  $ganchos */
    private function assertTemGanchos(TestResponse $resposta, array $ganchos): void
    {
        $resposta->assertOk();

        foreach ($ganchos as $gancho) {
            $this->assertStringContainsString($gancho, $resposta->getContent(), "Falta o gancho do JavaScript: {$gancho}");
        }
    }

    public function test_disponibilidade_publica(): void
    {
        $this->assertTemGanchos($this->get(route('disponibilidade.index')), self::DISPONIBILIDADE);
    }

    public function test_paciente_pede_consulta(): void
    {
        $paciente = Paciente::factory()->create();

        $this->assertTemGanchos(
            $this->actingAs($paciente->user)->get(route('paciente.pedidos.create')),
            [...self::DISPONIBILIDADE, ...self::MARCACAO],
        );
    }

    public function test_rececao_marca_consulta(): void
    {
        $this->assertTemGanchos(
            $this->actingAs($this->rececionista)->get(route('rececao.consultas.create')),
            [...self::DISPONIBILIDADE, ...self::MARCACAO, 'data-pesquisa-paciente', 'data-resultados-paciente', 'data-paciente-escolhido', 'data-texto-nenhum=', 'data-texto-utente=', 'name="paciente_id"'],
        );
    }

    public function test_rececao_reagenda_consulta(): void
    {
        $consulta = Consulta::factory()->confirmada()->for($this->medica)->create(['inicio' => '2026-11-23 09:30:00']);

        $this->assertTemGanchos(
            $this->actingAs($this->rececionista)->get(route('rececao.consultas.reagendamento', $consulta)),
            [...self::DISPONIBILIDADE, ...self::MARCACAO, 'data-sem-espera="true"'],
        );
    }

    public function test_rececao_lista_de_pedidos(): void
    {
        Consulta::factory()->pedida()->for($this->medica)->create(['inicio' => '2026-11-23 09:30:00']);

        $this->assertTemGanchos(
            $this->actingAs($this->rececionista)->get(route('rececao.pedidos.index')),
            ['data-pagina="rececao-pedidos"', 'data-ha-mais=', 'data-texto-erro=', 'data-vazio', '<tr data-pedido=', 'data-ajax="pedido"', 'name="motivo"', 'class="table-responsive'],
        );
    }

    public function test_rececao_estatisticas(): void
    {
        Consulta::factory()->realizada()->for($this->medica)->create(['inicio' => '2026-11-16 09:00:00']);

        $this->assertTemGanchos(
            $this->actingAs($this->rececionista)->get(route('rececao.estatisticas.index')),
            ['id="dados-estatisticas"', 'data-grafico="estados"'],
        );
    }

    public function test_layout_tem_o_token_csrf_e_o_contentor_dos_avisos(): void
    {
        $this->assertTemGanchos(
            $this->actingAs($this->rececionista)->get(route('rececao.pedidos.index')),
            ['<meta name="csrf-token"', '<main'],
        );
    }
}
