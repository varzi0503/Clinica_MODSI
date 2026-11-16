@extends('layouts.app')

@section('title', __('Pacientes'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
        <h1 class="h3 mb-0">{{ __('Pacientes') }}</h1>
        <a href="{{ route('rececao.pacientes.create') }}" class="btn btn-primary">{{ __('Nova ficha') }}</a>
    </div>

    <form method="GET" class="row g-2 align-items-end mb-3">
        <div class="col-sm-8 col-md-5">
            <label for="q" class="form-label">{{ __('Pesquisar') }}</label>
            <input type="search" id="q" name="q" class="form-control" value="{{ $filtros['q'] ?? '' }}"
                   placeholder="{{ __('Nome ou nº de utente') }}" maxlength="100">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-secondary">{{ __('Pesquisar') }}</button>
        </div>
    </form>

    @if ($pacientes->isEmpty())
        <p class="text-muted">{{ __('Nenhum paciente encontrado.') }}</p>
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Nome') }}</th>
                        <th>{{ __('Nº de utente') }}</th>
                        <th>{{ __('Telefone') }}</th>
                        <th>{{ __('Conta') }}</th>
                        <th class="text-end">{{ __('Ações') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pacientes as $paciente)
                        <tr>
                            <td>{{ $paciente->nome }}</td>
                            <td>{{ $paciente->num_utente }}</td>
                            <td>{{ $paciente->telefone ?? '—' }}</td>
                            <td>
                                @if ($paciente->user)
                                    {{ $paciente->user->email }}
                                @else
                                    <span class="badge text-bg-secondary">{{ __('Sem conta') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('rececao.pacientes.edit', $paciente) }}" class="btn btn-sm btn-outline-primary">{{ __('Editar') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $pacientes->links() }}
    @endif
@endsection
