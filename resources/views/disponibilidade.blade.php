@extends('layouts.app')

@section('title', __('Disponibilidade'))

@section('cabecalho')
    <h1>{{ __('Disponibilidade') }}</h1>
@endsection

@section('content')
    <div class="mc-sobe" data-pagina="disponibilidade"
         data-url="{{ route('disponibilidade.vagas') }}"
         data-locale="{{ str_replace('_', '-', app()->getLocale()) }}"
         data-texto-sem-vagas="{{ __('Sem vagas livres neste período.') }}"
         data-texto-erro="{{ __('Ocorreu um erro. Recarrega a página.') }}">

        <div class="card mb-4">
            <div class="card-body">
                <form class="row g-2 align-items-end" data-filtro novalidate>
                    <div class="col-sm-6 col-md-4">
                        <label for="especialidade_id" class="form-label">{{ __('Especialidade') }}</label>
                        <select id="especialidade_id" name="especialidade_id" class="form-select" required>
                            <option value="">{{ __('Escolha uma especialidade') }}</option>
                            @foreach ($especialidades as $especialidade)
                                <option value="{{ $especialidade->id }}">{{ $especialidade->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <label for="de" class="form-label">{{ __('A partir de') }}</label>
                        <input type="date" id="de" name="de" class="form-control"
                               value="{{ today()->toDateString() }}" min="{{ today()->toDateString() }}">
                    </div>
                </form>
            </div>
        </div>

        <div data-resultados aria-live="polite" class="mc-slots"></div>
    </div>
@endsection
