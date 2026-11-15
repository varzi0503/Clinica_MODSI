<?php

namespace App\Http\Controllers\Rececao;

use App\Enums\TipoProfissional;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rececao\AtualizarProfissionalRequest;
use App\Http\Requests\Rececao\CriarProfissionalRequest;
use App\Models\Especialidade;
use App\Models\Perfil;
use App\Models\Profissional;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * UC22 — a receção gere os profissionais de saúde (issue #70).
 *
 * A conta e a ficha nascem juntas, com o perfil "profissional" e pelo menos uma especialidade:
 * uma conta de profissional sem ficha entraria e ficaria sem funcionalidades (D26).
 * Ativar e desativar a conta continua a ser do administrador.
 */
class ProfissionalController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'tipo' => ['nullable', Rule::enum(TipoProfissional::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $profissionais = Profissional::with(['user', 'especialidades'])
            ->when($filtros['tipo'] ?? null, fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->when($filtros['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w
                ->where('num_cedula', 'like', "%{$t}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$t}%")->orWhere('email', 'like', "%{$t}%"))))
            ->orderBy(User::select('name')->whereColumn('users.id', 'profissionais.user_id'))
            ->paginate(20)
            ->withQueryString();

        return view('rececao.profissionais.index', [
            'profissionais' => $profissionais,
            'tipos' => TipoProfissional::cases(),
            'filtros' => $filtros,
        ]);
    }

    public function create(): View
    {
        return view('rececao.profissionais.create', [
            'tipos' => TipoProfissional::cases(),
            'especialidades' => Especialidade::orderBy('nome')->get(),
        ]);
    }

    public function store(CriarProfissionalRequest $request): RedirectResponse
    {
        try {
            $profissional = $this->criar($request->validated());
        } catch (UniqueConstraintViolationException) {
            // Email ou cédula registados por outro pedido entre a validação e a gravação.
            throw ValidationException::withMessages([
                'email' => __('Não foi possível criar o profissional: o email ou o nº de cédula acabou de ser registado.'),
            ]);
        }

        return redirect()->route('rececao.profissionais.index')
            ->with('success', __('Profissional :nome criado.', ['nome' => $profissional->user->name]));
    }

    public function edit(Profissional $profissional): View
    {
        return view('rececao.profissionais.edit', [
            'profissional' => $profissional->load(['user', 'especialidades']),
            'tipos' => TipoProfissional::cases(),
            'especialidades' => Especialidade::orderBy('nome')->get(),
        ]);
    }

    public function update(AtualizarProfissionalRequest $request, Profissional $profissional): RedirectResponse
    {
        $dados = $request->validated();

        try {
            // O nome é da conta (users.name); o resto é da ficha.
            DB::transaction(function () use ($profissional, $dados): void {
                $profissional->user->update(['name' => $dados['name']]);
                $profissional->update([
                    'tipo' => $dados['tipo'],
                    'num_cedula' => $dados['num_cedula'],
                    'contacto' => $dados['contacto'] ?? null,
                ]);
                $profissional->especialidades()->sync($dados['especialidades']);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'num_cedula' => __('Já existe um profissional deste tipo com este nº de cédula.'),
            ]);
        }

        return redirect()->route('rececao.profissionais.index')
            ->with('success', __('Profissional :nome atualizado.', ['nome' => $dados['name']]));
    }

    /**
     * Conta, ficha e especialidades na mesma transação: ou fica tudo, ou não fica nada.
     *
     * @param  array<string, mixed>  $dados
     */
    private function criar(array $dados): Profissional
    {
        return DB::transaction(function () use ($dados) {
            $user = User::create([
                'perfil_id' => Perfil::firstOrCreate(['nome' => Perfil::PROFISSIONAL])->id,
                'name' => $dados['name'],
                'email' => $dados['email'],
                'password' => $dados['password'], // o cast 'hashed' do modelo faz o hash
            ]);

            $profissional = Profissional::create([
                'user_id' => $user->id,
                'tipo' => $dados['tipo'],
                'num_cedula' => $dados['num_cedula'],
                'contacto' => $dados['contacto'] ?? null,
            ]);

            $profissional->especialidades()->attach($dados['especialidades']);

            return $profissional->setRelation('user', $user);
        });
    }
}
