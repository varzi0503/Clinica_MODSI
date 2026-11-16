@extends('layouts.app')

@section('title', __('Histórico do paciente'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <h1 class="h3 mb-0">{{ $paciente->nome }}</h1>
        <a href="{{ route('profissional.agenda.index') }}" class="btn btn-outline-secondary">{{ __('Voltar à agenda') }}</a>
    </div>
    <p class="text-muted mb-4">
        {{ __('Nº de utente') }} {{ $paciente->num_utente }}
        @if ($paciente->telefone) · {{ $paciente->telefone }} @endif
        <br>
        <span class="small">{{ __('Só aparecem as consultas deste paciente consigo.') }}</span>
    </p>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>{{ __('Data') }}</th>
                    <th>{{ __('Especialidade') }}</th>
                    <th>{{ __('Motivo da consulta') }}</th>
                    <th>{{ __('Estado') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($consultas as $consulta)
                    <tr>
                        <td class="text-nowrap">{{ $consulta->inicio->format('d/m/Y H:i') }}</td>
                        <td>{{ $consulta->especialidade->nome }}</td>
                        <td>{{ $consulta->motivo_consulta ?? '—' }}</td>
                        <td>
                            @include('partials.estado', ['estado' => $consulta->estado])
                            @if ($consulta->motivoDoEstado())
                                <div class="small text-muted">{{ __('Motivo') }}: {{ $consulta->motivoDoEstado() }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $consultas->links() }}
@endsection
