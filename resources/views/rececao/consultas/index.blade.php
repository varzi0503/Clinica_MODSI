@extends('layouts.app')

@section('title', __('Consultas'))

@section('cabecalho')
    <div class="mc-head-row">
        <h1>{{ __('Consultas') }}</h1>
        <a href="{{ route('rececao.consultas.create') }}" class="btn btn-primary">{{ __('Marcar consulta') }}</a>
    </div>
@endsection

@section('content')
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-sm-6 col-md-4">
                    <label for="profissional_id" class="form-label">{{ __('Profissional') }}</label>
                    <select id="profissional_id" name="profissional_id" class="form-select">
                        <option value="">{{ __('Todos') }}</option>
                        @foreach ($profissionais as $profissional)
                            <option value="{{ $profissional->id }}" @selected(($filtros['profissional_id'] ?? null) == $profissional->id)>{{ $profissional->user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-4 col-md-3">
                    <label for="de" class="form-label">{{ __('A partir de') }}</label>
                    <input type="date" id="de" name="de" class="form-control" value="{{ $filtros['de'] }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary">{{ __('Filtrar') }}</button>
                </div>
            </form>
        </div>
    </div>

    @if ($consultas->isEmpty())
        <p class="text-muted">{{ __('Não há consultas a partir desta data.') }}</p>
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Data') }}</th>
                        <th>{{ __('Paciente') }}</th>
                        <th>{{ __('Profissional') }}</th>
                        <th>{{ __('Especialidade') }}</th>
                        <th>{{ __('Estado') }}</th>
                        <th class="text-end">{{ __('Ações') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($consultas as $consulta)
                        @php $aberta = $consulta->estado->transicoesPermitidas() !== []; @endphp
                        <tr>
                            <td class="text-nowrap">{{ $consulta->inicio->format('d/m/Y H:i') }}</td>
                            <td>{{ $consulta->paciente->nome }}</td>
                            <td>{{ $consulta->profissional->user->name }}</td>
                            <td>{{ $consulta->especialidade->nome }}</td>
                            <td>@include('partials.estado', ['estado' => $consulta->estado])</td>
                            <td class="text-end text-nowrap">
                                @if (in_array($consulta->id, $reagendaveis, true))
                                    <a href="{{ route('rececao.consultas.reagendamento', $consulta) }}" class="btn btn-sm btn-outline-primary">{{ __('Reagendar') }}</a>
                                @endif
                                @if ($aberta)
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="collapse" data-bs-target="#cancelar-{{ $consulta->id }}"
                                            aria-expanded="false" aria-controls="cancelar-{{ $consulta->id }}">{{ __('Cancelar') }}</button>
                                @endif
                            </td>
                        </tr>
                        @if ($aberta)
                            <tr class="collapse {{ old('consulta_id') == $consulta->id ? 'show' : '' }}" id="cancelar-{{ $consulta->id }}">
                                <td colspan="6" class="bg-body-tertiary">
                                    <form method="POST" action="{{ route('rececao.consultas.cancelar', $consulta) }}" class="d-flex flex-wrap gap-2 align-items-start">
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
