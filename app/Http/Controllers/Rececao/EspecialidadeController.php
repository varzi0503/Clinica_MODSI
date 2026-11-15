<?php

namespace App\Http\Controllers\Rececao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rececao\GuardarEspecialidadeRequest;
use App\Models\Especialidade;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * UC23 — a receção gere as especialidades (issue #71).
 * Uma especialidade com profissionais associados não se remove.
 */
class EspecialidadeController extends Controller
{
    public function index(): View
    {
        return view('rececao.especialidades.index', [
            'especialidades' => Especialidade::withCount('profissionais')->orderBy('nome')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('rececao.especialidades.form', ['especialidade' => null]);
    }

    public function store(GuardarEspecialidadeRequest $request): RedirectResponse
    {
        $especialidade = Especialidade::create($request->validated());

        return redirect()->route('rececao.especialidades.index')
            ->with('success', __('Especialidade :nome criada.', ['nome' => $especialidade->nome]));
    }

    public function edit(Especialidade $especialidade): View
    {
        return view('rececao.especialidades.form', ['especialidade' => $especialidade]);
    }

    public function update(GuardarEspecialidadeRequest $request, Especialidade $especialidade): RedirectResponse
    {
        $especialidade->update($request->validated());

        return redirect()->route('rececao.especialidades.index')
            ->with('success', __('Especialidade :nome atualizada.', ['nome' => $especialidade->nome]));
    }

    public function destroy(Especialidade $especialidade): RedirectResponse
    {
        $aviso = __('Não pode remover :nome: há profissionais associados a esta especialidade.', ['nome' => $especialidade->nome]);

        if ($especialidade->profissionais()->exists()) {
            return back()->with('error', $aviso);
        }

        try {
            $especialidade->delete();
        } catch (QueryException) {
            // Um profissional foi associado entre a verificação e a remoção: a chave estrangeira recusa.
            return back()->with('error', $aviso);
        }

        return redirect()->route('rececao.especialidades.index')
            ->with('success', __('Especialidade :nome removida.', ['nome' => $especialidade->nome]));
    }
}
