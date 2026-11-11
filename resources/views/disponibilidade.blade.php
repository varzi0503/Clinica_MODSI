@extends('layouts.app')

@section('title', __('Disponibilidade'))

@section('content')
    <h1 class="h3 mb-4">{{ __('Disponibilidade') }}</h1>

    <div data-pagina="disponibilidade"
         data-url="{{ route('disponibilidade.vagas') }}"
         data-locale="{{ str_replace('_', '-', app()->getLocale()) }}"
         data-texto-sem-vagas="{{ __('Sem vagas livres neste período.') }}"
         data-texto-erro="{{ __('Ocorreu um erro. Recarrega a página.') }}">

        <form class="row g-2 align-items-end mb-4" data-filtro novalidate>
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

        <div data-resultados aria-live="polite"></div>
    </div>
@endsection
