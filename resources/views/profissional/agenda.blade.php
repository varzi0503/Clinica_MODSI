@extends('layouts.app')

@section('title', __('Agenda'))

@php
    $cores = [
        'pedida' => 'text-bg-warning',
        'confirmada' => 'text-bg-primary',
        'realizada' => 'text-bg-success',
        'faltou' => 'text-bg-secondary',
    ];
    $locale = str_replace('_', '-', app()->getLocale());
@endphp

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
        <h1 class="h3 mb-0">{{ __('Agenda') }}</h1>

        <div class="d-flex flex-wrap gap-2">
            <div class="btn-group" role="group" aria-label="{{ __('Vista') }}">
                <a href="{{ route('profissional.agenda.index', ['vista' => 'dia', 'data' => $data]) }}"
                   class="btn btn-sm {{ $vista === 'dia' ? 'btn-primary' : 'btn-outline-primary' }}">{{ __('Dia') }}</a>
                <a href="{{ route('profissional.agenda.index', ['vista' => 'semana', 'data' => $data]) }}"
                   class="btn btn-sm {{ $vista === 'semana' ? 'btn-primary' : 'btn-outline-primary' }}">{{ __('Semana') }}</a>
            </div>
            <div class="btn-group" role="group" aria-label="{{ __('Navegação') }}">
                <a href="{{ route('profissional.agenda.index', ['vista' => $vista, 'data' => $anterior]) }}"
                   class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Anterior') }}">&larr;</a>
                <a href="{{ route('profissional.agenda.index', ['vista' => $vista]) }}"
                   class="btn btn-sm btn-outline-secondary">{{ __('Hoje') }}</a>
                <a href="{{ route('profissional.agenda.index', ['vista' => $vista, 'data' => $seguinte]) }}"
                   class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Seguinte') }}">&rarr;</a>
            </div>
        </div>
    </div>

    <p class="text-muted">
        @if ($vista === 'dia')
            {{ $de->locale($locale)->isoFormat('dddd, LL') }}
        @else
            {{ $de->format('d/m') }} – {{ $ate->format('d/m/Y') }}
        @endif
    </p>

    @foreach ($dias as $dia)
        @php $doDia = $consultas->get($dia->toDateString(), collect()); @endphp

        <section class="mb-4" @if ($dia->isToday()) aria-current="date" @endif>
            @if ($vista === 'semana')
                <h2 class="h6 text-capitalize border-bottom pb-1 {{ $dia->isToday() ? 'text-primary fw-bold' : '' }}">
                    {{ $dia->locale($locale)->isoFormat('dddd, D MMM') }}
                </h2>
            @endif

            @if ($doDia->isEmpty())
                <p class="small text-muted mb-0">{{ __('Sem consultas.') }}</p>
            @else
                <ul class="list-group">
                    @foreach ($doDia as $consulta)
                        <li class="list-group-item d-flex flex-wrap gap-3 align-items-start">
                            <span class="fw-semibold" style="min-width: 3.5rem">{{ $consulta->inicio->format('H:i') }}</span>
                            <div class="flex-grow-1">
                                <div>{{ $consulta->paciente->nome }}
                                    <small class="text-muted">· {{ $consulta->especialidade->nome }}</small>
                                </div>
                                @if ($consulta->motivo_consulta)
                                    <div class="small text-muted">{{ $consulta->motivo_consulta }}</div>
                                @endif
                            </div>
                            <span class="badge {{ $cores[$consulta->estado->value] ?? 'text-bg-light' }}">{{ $consulta->estado->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endforeach
@endsection
