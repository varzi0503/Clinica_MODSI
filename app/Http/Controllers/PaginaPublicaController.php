<?php

namespace App\Http\Controllers;

use App\Enums\TipoProfissional;
use App\Models\Especialidade;
use App\Models\Profissional;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * UC01–UC03 — páginas públicas (issue #52): informação da clínica, especialidades e profissionais.
 * Não exigem sessão e não mostram dados de pacientes nem contactos pessoais dos profissionais:
 * de cada profissional aparece só o nome, o tipo e as especialidades.
 */
class PaginaPublicaController extends Controller
{
    /** UC01 — contactos, horário e serviços da clínica. */
    public function inicio(): View
    {
        return view('home', [
            'clinica' => config('clinica'),
            'especialidades' => Especialidade::has('profissionais')->orderBy('nome')->get(),
        ]);
    }

    /** UC02 — especialidades que a clínica oferece, ou seja, as que têm profissionais. */
    public function especialidades(): View
    {
        return view('especialidades', [
            'especialidades' => Especialidade::has('profissionais')->withCount('profissionais')->orderBy('nome')->paginate(12),
        ]);
    }

    /** UC03 — profissionais de saúde e as suas especialidades. */
    public function profissionais(Request $request): View
    {
        $filtros = $request->validate([
            'especialidade_id' => ['nullable', 'integer', 'exists:especialidades,id'],
            'tipo' => ['nullable', Rule::enum(TipoProfissional::class)],
        ]);

        $profissionais = Profissional::with(['user', 'especialidades'])
            ->has('especialidades')
            // Uma conta desativada pelo administrador deixa de aparecer ao público.
            ->whereRelation('user', 'ativo', true)
            ->when($filtros['especialidade_id'] ?? null, fn ($q, $id) => $q->whereRelation('especialidades', 'especialidades.id', $id))
            ->when($filtros['tipo'] ?? null, fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->orderBy(User::select('name')->whereColumn('users.id', 'profissionais.user_id'))
            ->paginate(12)
            ->withQueryString();

        return view('profissionais', [
            'profissionais' => $profissionais,
            'especialidades' => Especialidade::has('profissionais')->orderBy('nome')->get(),
            'tipos' => TipoProfissional::cases(),
            'filtros' => $filtros,
        ]);
    }
}
