@extends('layouts.app')

@section('title', __('As minhas consultas'))

@section('cabecalho')
    <div class="mc-head-row">
        <h1>{{ __('As minhas consultas') }}</h1>
        <a href="{{ route('paciente.pedidos.create') }}" class="btn btn-primary">{{ __('Pedir consulta') }}</a>
    </div>
@endsection

@section('content')
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link {{ $passadas ? '' : 'active' }}" @if (! $passadas) aria-current="page" @endif
               href="{{ route('paciente.consultas.index') }}">{{ __('Próximas') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $passadas ? 'active' : '' }}" @if ($passadas) aria-current="page" @endif
               href="{{ route('paciente.consultas.index', ['vista' => 'passadas']) }}">{{ __('Passadas') }}</a>
        </li>
    </ul>

    @unless ($passadas)
        <p class="text-muted small mb-4">{{ __('Pode cancelar um pedido ou uma consulta confirmada até :horas horas antes. Depois disso, contacte a receção.', ['horas' => $horas]) }}</p>
    @endunless

    @if ($consultas->isEmpty())
        <p class="text-muted">{{ $passadas ? __('Ainda não tem consultas passadas.') : __('Não tem consultas marcadas.') }}</p>
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
                            <td class="text-nowrap"><a href="{{ route('paciente.consultas.show', $consulta) }}" title="{{ __('Abrir a consulta') }}">{{ $consulta->inicio->format('d/m/Y H:i') }}</a></td>
                            <td>{{ $consulta->profissional->user->name }}</td>
                            <td>{{ $consulta->especialidade->nome }}</td>
                            <td>
                                @include('partials.estado', ['estado' => $consulta->estado])
                                @if ($consulta->motivoDoEstado())
                                    <div class="small text-muted">{{ __('Motivo') }}: {{ $consulta->motivoDoEstado() }}</div>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($passadas)
                                    {{-- O histórico é só de leitura. --}}
                                @elseif (in_array($consulta->id, $cancelaveis, true))
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

        {{ $consultas->links() }}
    @endif
@endsection
