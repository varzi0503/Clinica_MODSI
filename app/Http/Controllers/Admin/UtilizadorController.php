<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Perfil;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * UC21 — gerir utilizadores (issue #69) e UC24 — atribuir perfis (issue #72).
 *
 * O administrador só cria e atribui os perfis administrador e rececionista:
 * profissionais nascem com a ficha na receção (UC22) e pacientes no registo.
 * Contas com ficha de paciente ou de profissional não mudam de perfil (a ficha
 * ficaria órfã); só se ativam e desativam. O administrador não se desativa nem
 * muda o próprio perfil, para a clínica nunca ficar sem administração.
 */
class UtilizadorController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'perfil' => ['nullable', Rule::in(Perfil::NOMES)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $utilizadores = User::with(['perfil', 'paciente', 'profissional'])
            ->when($filtros['perfil'] ?? null, fn ($q, $p) => $q->whereRelation('perfil', 'nome', $p))
            ->when($filtros['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$t}%")
                ->orWhere('email', 'like', "%{$t}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.utilizadores.index', [
            'utilizadores' => $utilizadores,
            'perfis' => Perfil::orderBy('nome')->get(),
            'atribuiveis' => Perfil::whereIn('nome', Perfil::ATRIBUIVEIS_PELO_ADMIN)->orderBy('nome')->get(),
            'filtros' => $filtros,
        ]);
    }

    public function create(): View
    {
        return view('admin.utilizadores.create', [
            'perfis' => Perfil::whereIn('nome', Perfil::ATRIBUIVEIS_PELO_ADMIN)->orderBy('nome')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'perfil_id' => ['required', Rule::exists('perfis', 'id')->whereIn('nome', Perfil::ATRIBUIVEIS_PELO_ADMIN)],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        User::create($dados);

        return redirect()->route('admin.utilizadores.index')->with('success', __('Utilizador criado.'));
    }

    public function atualizarPerfil(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', __('Não pode alterar o seu próprio perfil.'));
        }

        if ($user->paciente || $user->profissional) {
            return back()->with('error', __('Esta conta tem ficha de paciente ou de profissional: o perfil não pode ser alterado.'));
        }

        $dados = $request->validate([
            'perfil_id' => ['required', Rule::exists('perfis', 'id')->whereIn('nome', Perfil::ATRIBUIVEIS_PELO_ADMIN)],
        ]);

        $user->forceFill(['perfil_id' => $dados['perfil_id']])->save();

        return back()->with('success', __('Perfil de :nome alterado.', ['nome' => $user->name]));
    }

    public function ativar(User $user): RedirectResponse
    {
        // Reativar é a altura certa para limpar um bloqueio por tentativas falhadas.
        $user->forceFill(['ativo' => true, 'tentativas_falhadas' => 0, 'bloqueado_ate' => null])->save();

        return back()->with('success', __('Conta de :nome ativada.', ['nome' => $user->name]));
    }

    public function desativar(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', __('Não pode desativar a sua própria conta.'));
        }

        $user->forceFill(['ativo' => false])->save();

        return back()->with('success', __('Conta de :nome desativada.', ['nome' => $user->name]));
    }
}
