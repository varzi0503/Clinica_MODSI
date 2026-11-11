@extends('layouts.app')

@section('title', __('Utilizadores'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
        <h1 class="h3 mb-0">{{ __('Utilizadores') }}</h1>
        <a href="{{ route('admin.utilizadores.create') }}" class="btn btn-primary">{{ __('Novo utilizador') }}</a>
    </div>

    <form method="GET" class="row g-2 align-items-end mb-3">
        <div class="col-sm-6 col-md-4">
            <label for="q" class="form-label">{{ __('Pesquisar') }}</label>
            <input type="search" id="q" name="q" class="form-control" value="{{ $filtros['q'] ?? '' }}"
                   placeholder="{{ __('Nome ou email') }}" maxlength="100">
        </div>
        <div class="col-sm-4 col-md-3">
            <label for="perfil" class="form-label">{{ __('Perfil') }}</label>
            <select id="perfil" name="perfil" class="form-select">
                <option value="">{{ __('Todos') }}</option>
                @foreach ($perfis as $perfil)
                    <option value="{{ $perfil->nome }}" @selected(($filtros['perfil'] ?? '') === $perfil->nome)>{{ __(ucfirst($perfil->nome)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-secondary">{{ __('Filtrar') }}</button>
        </div>
    </form>

    @if ($utilizadores->isEmpty())
        <p class="text-muted">{{ __('Nenhum utilizador encontrado.') }}</p>
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Nome') }}</th>
                        <th>{{ __('Email') }}</th>
                        <th>{{ __('Perfil') }}</th>
                        <th>{{ __('Estado') }}</th>
                        <th class="text-end">{{ __('Ações') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($utilizadores as $utilizador)
                        @php
                            $proprio = $utilizador->is(auth()->user());
                            $comFicha = $utilizador->paciente || $utilizador->profissional;
                        @endphp
                        <tr>
                            <td>{{ $utilizador->name }} @if ($proprio) <small class="text-muted">({{ __('você') }})</small> @endif</td>
                            <td>{{ $utilizador->email }}</td>
                            <td>
                                @if ($proprio || $comFicha)
                                    {{ __(ucfirst($utilizador->perfil->nome)) }}
                                @else
                                    <form method="POST" action="{{ route('admin.utilizadores.perfil', $utilizador) }}" class="d-flex gap-1">
                                        @csrf
                                        @method('PATCH')
                                        <select name="perfil_id" class="form-select form-select-sm" aria-label="{{ __('Perfil') }}">
                                            @foreach ($atribuiveis as $perfil)
                                                <option value="{{ $perfil->id }}" @selected($utilizador->perfil_id === $perfil->id)>{{ __(ucfirst($perfil->nome)) }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Guardar') }}</button>
                                    </form>
                                @endif
                            </td>
                            <td>
                                @if (! $utilizador->ativo)
                                    <span class="badge text-bg-secondary">{{ __('Desativada') }}</span>
                                @elseif ($utilizador->estaBloqueado())
                                    <span class="badge text-bg-warning">{{ __('Bloqueada') }}</span>
                                @else
                                    <span class="badge text-bg-success">{{ __('Ativa') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($utilizador->ativo && ! $utilizador->estaBloqueado())
                                    @unless ($proprio)
                                        <form method="POST" action="{{ route('admin.utilizadores.desativar', $utilizador) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Desativar') }}</button>
                                        </form>
                                    @endunless
                                @else
                                    <form method="POST" action="{{ route('admin.utilizadores.ativar', $utilizador) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-success">{{ __('Ativar') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $utilizadores->links() }}
    @endif
@endsection
