@extends('layouts.app')

@section('title', __('Especialidades'))

@section('cabecalho')
    <h1>{{ __('Especialidades') }}</h1>
@endsection

@section('content')
    @if ($especialidades->isEmpty())
        <p class="text-muted">{{ __('As especialidades serão anunciadas em breve.') }}</p>
    @else
        <div class="row g-3 mb-3">
            @foreach ($especialidades as $especialidade)
                <div class="col-md-6 col-lg-4">
                    <div class="border rounded p-3 h-100 d-flex flex-column">
                        <h2 class="h5">{{ $especialidade->nome }}</h2>
                        @if ($especialidade->descricao)
                            <p class="text-body-secondary">{{ $especialidade->descricao }}</p>
                        @endif
                        <div class="mt-auto d-flex flex-wrap gap-2">
                            <a href="{{ route('profissionais.index', ['especialidade_id' => $especialidade->id]) }}" class="btn btn-sm btn-outline-primary">
                                {{ __('Profissionais') }} ({{ $especialidade->profissionais_count }})
                            </a>
                            <a href="{{ route('disponibilidade.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Ver disponibilidade') }}</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{ $especialidades->links() }}
    @endif
@endsection
