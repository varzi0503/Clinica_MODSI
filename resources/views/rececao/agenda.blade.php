@extends('layouts.app')

@section('title', __('Agenda geral'))

@php
    $locale = str_replace('_', '-', app()->getLocale());
    $filtro = array_filter(['profissional_id' => $profissionalId]);
@endphp

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="h3 mb-0">{{ __('Agenda geral') }}</h1>

        <div class="d-flex flex-wrap gap-2">
            <div class="btn-group" role="group" aria-label="{{ __('Vista') }}">
                <a href="{{ route('rececao.agenda.index', $filtro + ['vista' => 'dia', 'data' => $data]) }}"
                   class="btn btn-sm {{ $vista === 'dia' ? 'btn-primary' : 'btn-outline-primary' }}">{{ __('Dia') }}</a>
                <a href="{{ route('rececao.agenda.index', $filtro + ['vista' => 'semana', 'data' => $data]) }}"
                   class="btn btn-sm {{ $vista === 'semana' ? 'btn-primary' : 'btn-outline-primary' }}">{{ __('Semana') }}</a>
            </div>
            <div class="btn-group" role="group" aria-label="{{ __('Navegação') }}">
                <a href="{{ route('rececao.agenda.index', $filtro + ['vista' => $vista, 'data' => $anterior]) }}"
                   class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Anterior') }}">&larr;</a>
                <a href="{{ route('rececao.agenda.index', $filtro + ['vista' => $vista]) }}"
                   class="btn btn-sm btn-outline-secondary">{{ __('Hoje') }}</a>
                <a href="{{ route('rececao.agenda.index', $filtro + ['vista' => $vista, 'data' => $seguinte]) }}"
                   class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Seguinte') }}">&rarr;</a>
            </div>
        </div>
    </div>

    <form method="GET" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="vista" value="{{ $vista }}">
        <div class="col-sm-6 col-md-4">
            <label for="profissional_id" class="form-label">{{ __('Profissional') }}</label>
            <select id="profissional_id" name="profissional_id" class="form-select">
                <option value="">{{ __('Todos') }}</option>
                @foreach ($profissionais as $profissional)
                    <option value="{{ $profissional->id }}" @selected($profissionalId == $profissional->id)>{{ $profissional->user->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-4 col-md-3">
            <label for="data" class="form-label">{{ __('Data') }}</label>
            <input type="date" id="data" name="data" class="form-control" value="{{ $data }}">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-secondary">{{ __('Filtrar') }}</button>
        </div>
    </form>

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
                <div class="row g-3">
                    @foreach ($doDia as $doProfissional)
                        <div class="col-md-6 col-xl-4">
                            <div class="border rounded h-100">
                                <div class="px-3 py-2 border-bottom bg-body-tertiary fw-semibold">
                                    {{ $doProfissional->first()->profissional_nome }}
                                    <span class="badge text-bg-light border">{{ $doProfissional->count() }}</span>
                                </div>
                                <ul class="list-group list-group-flush">
                                    @foreach ($doProfissional as $consulta)
                                        <li class="list-group-item d-flex gap-2 align-items-start">
                                            <span class="fw-semibold" style="min-width: 3.25rem">{{ substr($consulta->inicio, 11, 5) }}</span>
                                            <div class="flex-grow-1">
                                                {{ $consulta->paciente_nome }}
                                                <div class="small text-muted">{{ $consulta->especialidade_nome }}</div>
                                            </div>
                                            @include('partials.estado', ['estado' => \App\Enums\EstadoConsulta::from($consulta->estado)])
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach

    <p class="small text-muted">{{ __('Para cancelar ou reagendar, use a página Consultas.') }} <a href="{{ route('rececao.consultas.index', ['de' => $de->toDateString()] + $filtro) }}">{{ __('Consultas') }}</a></p>
@endsection
