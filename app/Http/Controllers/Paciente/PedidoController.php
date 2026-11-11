<?php

namespace App\Http\Controllers\Paciente;

use App\Enums\EstadoConsulta;
use App\Http\Controllers\Controller;
use App\Models\Consulta;
use App\Models\Especialidade;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Services\Disponibilidade;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * UC07 — o paciente pede uma consulta (issue #53).
 * O pedido fica "pedida" e aparece na lista da receção (UC16), que o confirma
 * ou rejeita. O paciente é sempre o da conta autenticada: um paciente_id vindo
 * do formulário é ignorado. Pedir uma vaga já ocupada (lista de espera) é do #75.
 */
class PedidoController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->paciente($request)) {
            return $this->semFicha();
        }

        return view('paciente.pedir', [
            'especialidades' => Especialidade::has('profissionais')->orderBy('nome')->get(),
        ]);
    }

    public function store(Request $request, Disponibilidade $disponibilidade): RedirectResponse
    {
        $paciente = $this->paciente($request);
        if (! $paciente) {
            return $this->semFicha();
        }

        $dados = $request->validate([
            'especialidade_id' => ['required', 'integer', 'exists:especialidades,id'],
            'profissional_id' => ['required', 'integer', 'exists:profissionais,id'],
            'inicio' => ['required', 'date_format:Y-m-d\TH:i'],
            'motivo_consulta' => ['nullable', 'string', 'max:1000'],
        ]);

        $profissional = Profissional::findOrFail($dados['profissional_id']);

        if (! $profissional->especialidades()->whereKey($dados['especialidade_id'])->exists()) {
            throw ValidationException::withMessages([
                'especialidade_id' => __('O profissional escolhido não exerce esta especialidade.'),
            ]);
        }

        // O '|' põe os segundos a zero (sem ele, o PHP preenche-os com a hora atual).
        $inicio = CarbonImmutable::createFromFormat('Y-m-d\TH:i|', $dados['inicio']);

        if (! $disponibilidade->estaLivre($profissional, $inicio)) {
            throw ValidationException::withMessages([
                'inicio' => __('Esta vaga já não está disponível.'),
            ]);
        }

        try {
            Consulta::registarPedido([
                'paciente_id' => $paciente->id,
                'profissional_id' => $profissional->id,
                'especialidade_id' => $dados['especialidade_id'],
                'inicio' => $inicio,
                'motivo_consulta' => $dados['motivo_consulta'] ?? null,
            ], EstadoConsulta::Pedida, $request->user());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'inicio' => __('Já tem uma consulta a essa hora, ou a vaga acabou de ser ocupada.'),
            ]);
        }

        return redirect()->route('paciente.pedidos.create')->with('success', __('Pedido enviado para :data com :profissional. A receção vai confirmá-lo.', [
            'data' => $inicio->format('d/m/Y H:i'),
            'profissional' => $profissional->user->name,
        ]));
    }

    private function paciente(Request $request): ?Paciente
    {
        return $request->user()->paciente;
    }

    private function semFicha(): RedirectResponse
    {
        return redirect()->route('home')
            ->with('error', __('A sua conta não tem ficha de paciente associada. Contacte a receção.'));
    }
}
