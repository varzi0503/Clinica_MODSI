@extends('layouts.app')

@section('title', __('Consulta'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="h3 mb-0">{{ __('Consulta') }} · {{ $consulta->inicio->format('d/m/Y H:i') }}</h1>
        <a href="{{ route('paciente.consultas.index', $consulta->inicio->isPast() ? ['vista' => 'passadas'] : []) }}" class="btn btn-outline-secondary">{{ __('Voltar') }}</a>
    </div>

    <dl class="row mb-4">
        <dt class="col-sm-3">{{ __('Profissional') }}</dt>
        <dd class="col-sm-9">{{ $consulta->profissional->user->name }}</dd>
        <dt class="col-sm-3">{{ __('Especialidade') }}</dt>
        <dd class="col-sm-9">{{ $consulta->especialidade->nome }}</dd>
        <dt class="col-sm-3">{{ __('Estado') }}</dt>
        <dd class="col-sm-9">
            @include('partials.estado', ['estado' => $consulta->estado])
            @if ($consulta->motivoDoEstado()) <span class="small text-muted">{{ __('Motivo') }}: {{ $consulta->motivoDoEstado() }}</span> @endif
        </dd>
        <dt class="col-sm-3">{{ __('Motivo da consulta') }}</dt>
        <dd class="col-sm-9">{{ $consulta->motivo_consulta ?? '—' }}</dd>
    </dl>

    <p class="small text-muted">{{ __('Pode juntar análises, exames ou outros documentos úteis para a consulta. Só os vê você e o profissional desta consulta.') }}</p>
    @include('partials.documentos', ['consulta' => $consulta])
@endsection
