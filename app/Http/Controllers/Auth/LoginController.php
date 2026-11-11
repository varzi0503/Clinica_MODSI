<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credenciais = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $credenciais['email'])->first();

        if ($user && ! $user->ativo) {
            return $this->recusar(__('A conta está desativada. Contacte a receção.'));
        }

        if ($user && $user->estaBloqueado()) {
            return $this->recusar($this->mensagemBloqueio($user));
        }

        if (! Auth::attempt($credenciais)) {
            $user?->registarLoginFalhado();

            if ($user && $user->estaBloqueado()) {
                return $this->recusar($this->mensagemBloqueio($user));
            }

            return $this->recusar(__('As credenciais indicadas não estão corretas.'));
        }

        // Nova sessão após autenticar, contra fixação de sessão.
        $request->session()->regenerate();
        $request->user()->registarLoginComSucesso();

        return redirect()->intended(route('home'))->with('success', __('Sessão iniciada.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', __('Sessão terminada.'));
    }

    private function recusar(string $mensagem): RedirectResponse
    {
        return back()->withErrors(['email' => $mensagem])->onlyInput('email');
    }

    private function mensagemBloqueio(User $user): string
    {
        return __('Conta bloqueada por tentativas falhadas. Tente novamente às :hora.', [
            'hora' => $user->bloqueado_ate->format('H:i'),
        ]);
    }
}
