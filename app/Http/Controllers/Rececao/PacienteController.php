<?php

namespace App\Http\Controllers\Rececao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rececao\GuardarPacienteRequest;
use App\Models\Paciente;
use App\Models\Perfil;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * UC19 — a receção gere as fichas de pacientes (issue #58): pesquisa, cria fichas de balcão,
 * edita e associa uma conta já registada a uma ficha sem conta (D11, D35).
 */
class PacienteController extends Controller
{
    public const RESULTADOS_DA_PESQUISA = 10;

    public function index(Request $request): View
    {
        $filtros = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        return view('rececao.pacientes.index', [
            'pacientes' => $this->pesquisar($filtros['q'] ?? null)->with('user')->paginate(20)->withQueryString(),
            'filtros' => $filtros,
        ]);
    }

    /** Pesquisa para a página de marcar consulta: poucos resultados, só o que identifica a ficha. */
    public function pesquisa(Request $request): JsonResponse
    {
        $dados = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);

        return response()->json([
            'pacientes' => $this->pesquisar($dados['q'])
                ->limit(self::RESULTADOS_DA_PESQUISA)
                ->get(['id', 'nome', 'num_utente'])
                ->map(fn (Paciente $p) => ['id' => $p->id, 'nome' => $p->nome, 'num_utente' => $p->num_utente]),
        ]);
    }

    public function create(): View
    {
        return view('rececao.pacientes.form', ['paciente' => null]);
    }

    public function store(GuardarPacienteRequest $request): RedirectResponse
    {
        $paciente = Paciente::create($request->validated());

        return redirect()->route('rececao.pacientes.index')
            ->with('success', __('Ficha de :nome criada.', ['nome' => $paciente->nome]));
    }

    public function edit(Paciente $paciente): View
    {
        return view('rececao.pacientes.form', ['paciente' => $paciente->load('user')]);
    }

    public function update(GuardarPacienteRequest $request, Paciente $paciente): RedirectResponse
    {
        // O nome existe na conta e na ficha: muda nos dois sítios ou em nenhum (D10).
        DB::transaction(function () use ($request, $paciente): void {
            $paciente->update($request->validated());
            $paciente->user?->update(['name' => $paciente->nome]);
        });

        return redirect()->route('rececao.pacientes.index')
            ->with('success', __('Ficha de :nome atualizada.', ['nome' => $paciente->nome]));
    }

    /**
     * Associa a uma ficha sem conta uma conta de paciente já registada e ainda sem ficha.
     * É a receção que o faz, depois de confirmar a identidade da pessoa (D11).
     */
    public function associarConta(Request $request, Paciente $paciente): RedirectResponse
    {
        $dados = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        if ($paciente->user_id !== null) {
            return back()->with('error', __('Esta ficha já tem uma conta associada.'));
        }

        $user = User::where('email', $dados['email'])->whereRelation('perfil', 'nome', Perfil::PACIENTE)->first();

        if ($user === null) {
            throw ValidationException::withMessages(['email' => __('Não existe nenhuma conta de paciente com este email.')]);
        }

        if ($user->paciente()->exists()) {
            throw ValidationException::withMessages(['email' => __('Essa conta já está associada a outra ficha.')]);
        }

        try {
            DB::transaction(function () use ($paciente, $user): void {
                $paciente->forceFill(['user_id' => $user->id])->save();
                // O nome da ficha foi confirmado ao balcão: passa a ser também o da conta (D10).
                $user->update(['name' => $paciente->nome]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => __('Essa conta já está associada a outra ficha.')]);
        }

        return redirect()->route('rececao.pacientes.edit', $paciente)
            ->with('success', __('Conta :email associada à ficha de :nome.', ['email' => $user->email, 'nome' => $paciente->nome]));
    }

    /**
     * Fichas cujo nome ou nº de utente contém o texto, por ordem de nome.
     *
     * @return Builder<Paciente>
     */
    private function pesquisar(?string $texto): Builder
    {
        return Paciente::query()
            ->when($texto, fn (Builder $q, string $t) => $q->where(fn (Builder $w) => $w
                ->where('nome', 'like', "%{$t}%")
                ->orWhere('num_utente', 'like', "%{$t}%")))
            ->orderBy('nome')
            ->orderBy('id');
    }
}
