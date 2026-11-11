@extends('layouts.app')

@section('title', __('Pedir consulta'))

@section('content')
    <h1 class="h3 mb-4">{{ __('Pedir consulta') }}</h1>

    <div data-pagina="disponibilidade" data-selecionavel="true"
         data-url="{{ route('disponibilidade.vagas') }}"
         data-locale="{{ str_replace('_', '-', app()->getLocale()) }}"
         data-texto-sem-vagas="{{ __('Sem vagas livres neste período.') }}"
         data-texto-erro="{{ __('Ocorreu um erro. Recarrega a página.') }}">

        <form class="row g-2 align-items-end mb-3" data-filtro novalidate>
            <div class="col-sm-6 col-md-4">
                <label for="especialidade_id" class="form-label">{{ __('Especialidade') }}</label>
                <select id="especialidade_id" name="especialidade_id" class="form-select" required>
                    <option value="">{{ __('Escolha uma especialidade') }}</option>
                    @foreach ($especialidades as $especialidade)
                        <option value="{{ $especialidade->id }}" @selected(old('especialidade_id') == $especialidade->id)>{{ $especialidade->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-6 col-md-3">
                <label for="de" class="form-label">{{ __('A partir de') }}</label>
                <input type="date" id="de" name="de" class="form-control"
                       value="{{ today()->toDateString() }}" min="{{ today()->toDateString() }}">
            </div>
        </form>

        <div data-resultados aria-live="polite" class="mb-4"></div>

        <form method="POST" action="{{ route('paciente.pedidos.store') }}" data-marcacao class="border rounded p-3" novalidate>
            @csrf
            <input type="hidden" name="especialidade_id" value="{{ old('especialidade_id') }}">
            <input type="hidden" name="profissional_id" value="{{ old('profissional_id') }}">
            <input type="hidden" name="inicio" value="{{ old('inicio') }}">

            <p class="mb-3">
                <span class="text-muted">{{ __('Vaga escolhida') }}:</span>
                <strong data-escolha></strong>
            </p>
            @error('inicio')
                <div class="alert alert-danger py-2">{{ $message }}</div>
            @enderror
            @error('especialidade_id')
                <div class="alert alert-danger py-2">{{ $message }}</div>
            @enderror


            <div class="mb-3">
                <label for="motivo_consulta" class="form-label">{{ __('Motivo da consulta') }}</label>
                <textarea id="motivo_consulta" name="motivo_consulta" rows="2" maxlength="1000"
                          class="form-control @error('motivo_consulta') is-invalid @enderror">{{ old('motivo_consulta') }}</textarea>
            </div>

            <p class="small text-muted">{{ __('O pedido fica pendente até a receção o confirmar.') }}</p>
            <button type="submit" class="btn btn-primary" disabled>{{ __('Enviar pedido') }}</button>
        </form>
    </div>
@endsection
