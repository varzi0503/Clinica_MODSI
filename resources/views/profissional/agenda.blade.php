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

@section('cabecalho')
    <div class="mc-head-row">
        <h1>{{ __('Agenda') }}</h1>

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
    <p class="mt-2 mb-0">
        @if ($vista === 'dia')
            {{ $de->locale($locale)->isoFormat('dddd, LL') }}
        @else
            {{ $de->format('d/m') }} – {{ $ate->format('d/m/Y') }}
        @endif
    </p>
@endsection

@section('content')
    <div class="card mc-sobe overflow-hidden">

        @foreach ($dias as $dia)
            @php $doDia = $consultas->get($dia->toDateString(), collect()); @endphp

            <section @if ($dia->isToday()) aria-current="date" @endif>
                @if ($vista === 'semana' || $doDia->isEmpty())
                    <div class="mc-day {{ $dia->isToday() ? 'mc-today' : '' }}">
                        @if ($vista === 'semana')
                            <h2 class="mc-cap">{{ $dia->locale($locale)->isoFormat('dddd, D MMM') }}</h2>
                        @endif
                        @if ($doDia->isEmpty())
                            <span class="small text-muted">{{ __('Sem consultas.') }}</span>
                        @endif
                    </div>
                @endif

                @if ($doDia->isNotEmpty())
                    <ul class="list-group list-group-flush border-bottom">
                        @foreach ($doDia as $consulta)
                            <li class="list-group-item d-flex flex-wrap gap-3 align-items-start">
                                <a class="fw-semibold" style="min-width: 3.5rem" href="{{ route('profissional.consultas.show', $consulta) }}"
                                   title="{{ __('Abrir a consulta') }}">{{ $consulta->inicio->format('H:i') }}</a>
                                <div class="flex-grow-1">
                                    <div><a href="{{ route('profissional.pacientes.historico', $consulta->paciente) }}" title="{{ __('Histórico do paciente') }}">{{ $consulta->paciente->nome }}</a>
                                        <small class="text-muted">· {{ $consulta->especialidade->nome }}</small>
                                    </div>
                                    @if ($consulta->motivo_consulta)
                                        <div class="small text-muted">{{ $consulta->motivo_consulta }}</div>
                                    @endif
                                </div>
                                <div class="d-flex flex-wrap align-items-center justify-content-end gap-2">
                                    <span class="badge {{ $cores[$consulta->estado->value] ?? 'text-bg-light' }}">{{ $consulta->estado->label() }}</span>
                                    @if ($consulta->estado === \App\Enums\EstadoConsulta::Confirmada && ! $consulta->inicio->isFuture())
                                        <div class="d-flex gap-1">
                                            <form method="POST" action="{{ route('profissional.consultas.realizada', $consulta) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-success">{{ __('Realizada') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('profissional.consultas.faltou', $consulta) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Faltou') }}</button>
                                            </form>
                                        </div>
                                    @elseif ($consulta->estado === \App\Enums\EstadoConsulta::Confirmada)
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="collapse" data-bs-target="#cancelar-{{ $consulta->id }}"
                                                aria-expanded="false" aria-controls="cancelar-{{ $consulta->id }}">{{ __('Cancelar') }}</button>
                                    @endif
                                </div>
                                @if ($consulta->estado === \App\Enums\EstadoConsulta::Confirmada && $consulta->inicio->isFuture())
                                    <div class="collapse w-100 {{ old('consulta_id') == $consulta->id ? 'show' : '' }}" id="cancelar-{{ $consulta->id }}">
                                        <form method="POST" action="{{ route('profissional.consultas.cancelar', $consulta) }}" class="d-flex flex-wrap gap-2 align-items-start">
                                            @csrf
                                            <input type="hidden" name="consulta_id" value="{{ $consulta->id }}">
                                            <div class="flex-grow-1">
                                                <label for="motivo-{{ $consulta->id }}" class="visually-hidden">{{ __('Motivo') }}</label>
                                                <input type="text" id="motivo-{{ $consulta->id }}" name="motivo" maxlength="255" required
                                                       class="form-control form-control-sm @if (old('consulta_id') == $consulta->id) @error('motivo') is-invalid @enderror @endif"
                                                       placeholder="{{ __('Motivo do cancelamento') }}">
                                                @if (old('consulta_id') == $consulta->id)
                                                    @error('motivo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                @endif
                                            </div>
                                            <button type="submit" class="btn btn-sm btn-danger">{{ __('Confirmar cancelamento') }}</button>
                                        </form>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    </div>
@endsection
