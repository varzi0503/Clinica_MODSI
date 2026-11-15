@extends('layouts.app')

@section('title', __('Especialidades'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
        <h1 class="h3 mb-0">{{ __('Especialidades') }}</h1>
        <a href="{{ route('rececao.especialidades.create') }}" class="btn btn-primary">{{ __('Nova especialidade') }}</a>
    </div>

    @if ($especialidades->isEmpty())
        <p class="text-muted">{{ __('Ainda não há especialidades.') }}</p>
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Nome') }}</th>
                        <th>{{ __('Descrição') }}</th>
                        <th>{{ __('Profissionais') }}</th>
                        <th class="text-end">{{ __('Ações') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($especialidades as $especialidade)
                        <tr>
                            <td>{{ $especialidade->nome }}</td>
                            <td class="text-muted">{{ $especialidade->descricao ?? '—' }}</td>
                            <td>{{ $especialidade->profissionais_count }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('rececao.especialidades.edit', $especialidade) }}" class="btn btn-sm btn-outline-primary">{{ __('Editar') }}</a>
                                @if ($especialidade->profissionais_count === 0)
                                    <form method="POST" action="{{ route('rececao.especialidades.destroy', $especialidade) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Remover') }}</button>
                                    </form>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                                            title="{{ __('Tem profissionais associados.') }}">{{ __('Remover') }}</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $especialidades->links() }}
    @endif
@endsection
