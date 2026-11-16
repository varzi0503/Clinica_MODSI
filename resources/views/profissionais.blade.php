@extends('layouts.app')

@section('title', __('Profissionais de saúde'))

@section('content')
    <h1 class="h3 mb-4">{{ __('Profissionais de saúde') }}</h1>

    <form method="GET" class="row g-2 align-items-end mb-4">
        <div class="col-sm-6 col-md-4">
            <label for="especialidade_id" class="form-label">{{ __('Especialidade') }}</label>
            <select id="especialidade_id" name="especialidade_id" class="form-select">
                <option value="">{{ __('Todas') }}</option>
                @foreach ($especialidades as $especialidade)
                    <option value="{{ $especialidade->id }}" @selected(($filtros['especialidade_id'] ?? null) == $especialidade->id)>{{ $especialidade->nome }}</option>
                @endforeach
            </select>
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
        <div class="row g-3 mb-3">
            @foreach ($profissionais as $profissional)
                <div class="col-sm-6 col-lg-4">
                    <div class="border rounded p-3 h-100">
                        <h2 class="h6 mb-1">{{ $profissional->user->name }}</h2>
                        <div class="small text-muted mb-2">{{ $profissional->tipo->label() }}</div>
                        @foreach ($profissional->especialidades->sortBy('nome') as $especialidade)
                            <span class="badge text-bg-light border">{{ $especialidade->nome }}</span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{ $profissionais->links() }}
    @endif
@endsection
