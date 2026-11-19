@extends('layouts.app')

@section('title', __('Reagendar consulta'))

@section('cabecalho')
    <h1>{{ __('Reagendar consulta') }}</h1>
    <p class="mt-2 mb-0">
        {{ $consulta->paciente->nome }} · {{ $consulta->especialidade->nome }} ·
        {{ __('Marcada para :data com :profissional.', ['data' => $consulta->inicio->format('d/m/Y H:i'), 'profissional' => $consulta->profissional->user->name]) }}
    </p>
@endsection

@section('content')
    {{-- data-sem-espera: ao reagendar só se escolhem vagas livres. --}}
    <div class="row g-4 mc-sobe" data-pagina="disponibilidade" data-selecionavel="true" data-sem-espera="true"
         data-url="{{ route('disponibilidade.vagas') }}"
         data-locale="{{ str_replace('_', '-', app()->getLocale()) }}"
         data-texto-sem-vagas="{{ __('Sem vagas livres neste período.') }}"
         data-texto-espera="{{ __('Lista de espera') }}"
         data-texto-legenda=""
         data-texto-erro="{{ __('Ocorreu um erro. Recarrega a página.') }}">

        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <form class="row g-2 align-items-end" data-filtro novalidate>
                        <input type="hidden" name="especialidade_id" value="{{ $consulta->especialidade_id }}">
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
            <form method="POST" action="{{ route('rececao.consultas.reagendar', $consulta) }}" data-marcacao id="vaga-escolhida" class="card card-body mc-lateral" novalidate>
                @csrf
                <input type="hidden" name="especialidade_id" value="{{ $consulta->especialidade_id }}">
                <input type="hidden" name="profissional_id" value="{{ old('profissional_id') }}">
                <input type="hidden" name="inicio" value="{{ old('inicio') }}">
                <input type="hidden" name="lista_espera" value="0">

                <p class="mb-3">
                    <span class="text-muted">{{ __('Vaga escolhida') }}:</span>
                    <strong data-escolha></strong>
                </p>
                <div class="d-none" data-aviso-espera></div>
                @error('inicio')
                    <div class="alert alert-danger py-2">{{ $message }}</div>
                @enderror
                @error('especialidade_id')
                    <div class="alert alert-danger py-2">{{ $message }}</div>
                @enderror
                @error('profissional_id')
                    <div class="alert alert-danger py-2">{{ $message }}</div>
                @enderror

                <p class="small text-muted">{{ __('A consulta atual é cancelada com o motivo "Reagendada" e a vaga antiga fica livre.') }}</p>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary" disabled>{{ __('Reagendar') }}</button>
                    <a href="{{ route('rececao.consultas.index') }}" class="btn btn-outline-secondary">{{ __('Voltar') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection
