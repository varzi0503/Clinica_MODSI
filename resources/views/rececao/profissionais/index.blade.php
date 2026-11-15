@extends('layouts.app')

@section('title', __('Profissionais'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
        <h1 class="h3 mb-0">{{ __('Profissionais') }}</h1>
        <a href="{{ route('rececao.profissionais.create') }}" class="btn btn-primary">{{ __('Novo profissional') }}</a>
    </div>

    <form method="GET" class="row g-2 align-items-end mb-3">
        <div class="col-sm-6 col-md-4">
            <label for="q" class="form-label">{{ __('Pesquisar') }}</label>
            <input type="search" id="q" name="q" class="form-control" value="{{ $filtros['q'] ?? '' }}"
                   placeholder="{{ __('Nome, email ou nº de cédula') }}" maxlength="100">
        </div>
        <div class="col-sm-4 col-md-3">
            <label for="tipo" class="form-label">{{ __('Tipo') }}</label>
            <select id="tipo" name="tipo" class="form-select">
                <option value="">{{ __('Todos') }}</option>
                @foreach ($tipos as $tipo)
                    <option value="{{ $tipo->value }}" @selected(($filtros['tipo'] ?? '') === $tipo->value)>{{ $tipo->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-secondary">{{ __('Filtrar') }}</button>
        </div>
    </form>

    @if ($profissionais->isEmpty())
        <p class="text-muted">{{ __('Nenhum profissional encontrado.') }}</p>
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Nome') }}</th>
                        <th>{{ __('Tipo') }}</th>
                        <th>{{ __('Nº de cédula') }}</th>
                        <th>{{ __('Especialidades') }}</th>
                        <th>{{ __('Contacto') }}</th>
                        <th>{{ __('Conta') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($profissionais as $profissional)
                        <tr>
                            <td>
                                {{ $profissional->user->name }}
                                <div class="small text-muted">{{ $profissional->user->email }}</div>
                            </td>
                            <td>{{ $profissional->tipo->label() }}</td>
                            <td>{{ $profissional->num_cedula }}</td>
                            <td>{{ $profissional->especialidades->sortBy('nome')->pluck('nome')->join(', ') }}</td>
                            <td>{{ $profissional->contacto ?? '—' }}</td>
                            <td>
                                @if ($profissional->user->ativo)
                                    <span class="badge text-bg-success">{{ __('Ativa') }}</span>
                                @else
                                    <span class="badge text-bg-secondary">{{ __('Desativada') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $profissionais->links() }}
    @endif
@endsection
