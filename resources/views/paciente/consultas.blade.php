@extends('layouts.app')

@section('title', __('As minhas consultas'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <h1 class="h3 mb-0">{{ __('As minhas consultas') }}</h1>
        <a href="{{ route('paciente.pedidos.create') }}" class="btn btn-primary">{{ __('Pedir consulta') }}</a>
    </div>
    <p class="text-muted small mb-4">{{ __('Pode cancelar um pedido ou uma consulta confirmada até :horas horas antes. Depois disso, contacte a receção.', ['horas' => $horas]) }}</p>

    @if ($consultas->isEmpty())
        <p class="text-muted">{{ __('Não tem consultas marcadas.') }}</p>
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Data') }}</th>
                        <th>{{ __('Profissional') }}</th>
                        <th>{{ __('Especialidade') }}</th>
                        <th>{{ __('Estado') }}</th>
                        <th class="text-end">{{ __('Ações') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($consultas as $consulta)
                        <tr>
                            <td class="text-nowrap">{{ $consulta->inicio->format('d/m/Y H:i') }}</td>
                            <td>{{ $consulta->profissional->user->name }}</td>
                            <td>{{ $consulta->especialidade->nome }}</td>
                            <td>@include('partials.estado', ['estado' => $consulta->estado])</td>
                            <td class="text-end">
                                @if (in_array($consulta->id, $cancelaveis, true))
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="collapse" data-bs-target="#cancelar-{{ $consulta->id }}"
                                            aria-expanded="false" aria-controls="cancelar-{{ $consulta->id }}">{{ __('Cancelar') }}</button>
                                @elseif ($consulta->estado->ocupaVaga())
                                    <span class="small text-muted">{{ __('Já não pode ser cancelada aqui.') }}</span>
                                @endif
                            </td>
                        </tr>
                        @if (in_array($consulta->id, $cancelaveis, true))
                            <tr class="collapse" id="cancelar-{{ $consulta->id }}">
                                <td colspan="5" class="bg-body-tertiary">
                                    <form method="POST" action="{{ route('paciente.consultas.cancelar', $consulta) }}" class="d-flex flex-wrap gap-2 align-items-center">
                                        @csrf
                                        <label for="motivo-{{ $consulta->id }}" class="visually-hidden">{{ __('Motivo') }}</label>
                                        <input type="text" id="motivo-{{ $consulta->id }}" name="motivo" maxlength="255" class="form-control form-control-sm w-auto flex-grow-1"
                                               placeholder="{{ __('Motivo') }} ({{ __('opcional') }})">
                                        <button type="submit" class="btn btn-sm btn-danger">{{ __('Confirmar cancelamento') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
