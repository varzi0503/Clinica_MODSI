@extends('layouts.app')

@section('title', __('Pedir consulta'))

@section('cabecalho')
    <h1>{{ __('Pedir consulta') }}</h1>
@endsection

@section('content')
    <div class="row g-4 mc-sobe" data-pagina="disponibilidade" data-selecionavel="true"
         data-url="{{ route('disponibilidade.vagas') }}"
         data-locale="{{ str_replace('_', '-', app()->getLocale()) }}"
         data-texto-sem-vagas="{{ __('Sem vagas livres neste período.') }}"
         data-texto-espera="{{ __('Lista de espera') }}"
         data-texto-legenda="{{ __('As vagas a tracejado já estão ocupadas: pode ficar em lista de espera.') }}"
         data-texto-erro="{{ __('Ocorreu um erro. Recarrega a página.') }}">

        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <form class="row g-2 align-items-end" data-filtro novalidate>
                        <div class="col-sm-7">
                            <label for="especialidade_id" class="form-label">{{ __('Especialidade') }}</label>
                            <select id="especialidade_id" name="especialidade_id" class="form-select" required>
                                <option value="">{{ __('Escolha uma especialidade') }}</option>
                                @foreach ($especialidades as $especialidade)
                                    <option value="{{ $especialidade->id }}" @selected(old('especialidade_id') == $especialidade->id)>{{ $especialidade->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-5">
                            <label for="de" class="form-label">{{ __('A partir de') }}</label>
                            <input type="date" id="de" name="de" class="form-control"
                                   value="{{ today()->toDateString() }}" min="{{ today()->toDateString() }}">
                        </div>
                    </form>
                </div>
            </div>

            <a class="mc-skip" href="#vaga-escolhida">{{ __('Ir para a vaga escolhida') }} <span aria-hidden="true">↓</span></a>
            <div data-resultados aria-live="polite" class="mc-slots"></div>
        </div>

        <div class="col-lg-4">
            <form method="POST" action="{{ route('paciente.pedidos.store') }}" data-marcacao id="vaga-escolhida" class="card card-body mc-lateral" novalidate>
                @csrf
                <input type="hidden" name="especialidade_id" value="{{ old('especialidade_id') }}">
                <input type="hidden" name="profissional_id" value="{{ old('profissional_id') }}">
                <input type="hidden" name="inicio" value="{{ old('inicio') }}">
                <input type="hidden" name="lista_espera" value="{{ old('lista_espera', 0) }}">

                <p class="mb-3">
                    <span class="text-muted">{{ __('Vaga escolhida') }}:</span>
                    <strong data-escolha></strong>
                </p>
                <div class="alert alert-info py-2 d-none" data-aviso-espera>{{ __('Esta vaga já está ocupada. O pedido fica em lista de espera e só avança se a vaga ficar livre.') }}</div>
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
    </div>
@endsection
