<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Paciente;
use App\Models\Perfil;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * UC04 — registo de paciente (issue #48).
 *
 * O registo nunca se associa a uma ficha de balcão já existente: quem soubesse
 * o nº de utente de outra pessoa ficaria com acesso ao histórico dela (D11). Nesse
 * caso a conta nasce sem ficha e é a receção que as associa, depois de confirmar
 * quem é a pessoa (D35).
 */
class RegistoController extends Controller
{
    public function create(): View
    {
        return view('auth.registo');
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'num_utente' => ['required', 'digits:9'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // Já há uma ficha com este nº de utente (D35). Se tiver conta, o registo é recusado.
        // Se for uma ficha de balcão, a conta nasce sem ficha e é a receção que as associa.
        $ficha = Paciente::where('num_utente', $dados['num_utente'])->first();

        if ($ficha?->user_id !== null) {
            throw ValidationException::withMessages([
                'num_utente' => __('Já existe uma conta com este nº de utente.'),
            ]);
        }

        try {
            $user = $this->criarConta($dados, comFicha: $ficha === null);
        } catch (UniqueConstraintViolationException) {
            // Email ou nº de utente registado por outro pedido entre a validação e a gravação.
            throw ValidationException::withMessages([
                'email' => __('Não foi possível criar a conta: o email ou o nº de utente acabou de ser registado.'),
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        if ($ficha !== null) {
            return redirect()->route('home')
                ->with('warning', __('A sua conta foi criada, mas já existe uma ficha com este nº de utente. Dirija-se à receção para a associar à sua conta.'));
        }

        return redirect()->route('home')->with('success', __('Conta criada. Bem-vindo(a)!'));
    }

    /** @param  array<string, mixed>  $dados */
    private function criarConta(array $dados, bool $comFicha): User
    {
        return DB::transaction(function () use ($dados, $comFicha) {
            $user = User::create([
                'perfil_id' => Perfil::firstOrCreate(['nome' => 'paciente'])->id,
                'name' => $dados['nome'],
                'email' => $dados['email'],
                'password' => $dados['password'], // o cast 'hashed' do modelo faz o hash
            ]);

            if ($comFicha) {
                Paciente::create([
                    'user_id' => $user->id,
                    'nome' => $dados['nome'],
                    'num_utente' => $dados['num_utente'],
                    'telefone' => $dados['telefone'] ?? null,
                ]);
            }

            return $user;
        });
    }
}
