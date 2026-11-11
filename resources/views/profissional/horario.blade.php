@extends('layouts.app')

@section('title', __('O meu horário'))

@section('content')
    <h1 class="h3 mb-1">{{ __('O meu horário') }}</h1>
    <p class="text-muted mb-4">{{ __('Períodos de atendimento que se repetem todas as semanas, em vagas de 30 minutos.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            @if ($horarios->isEmpty())
                <p class="text-muted">{{ __('Ainda não definiu nenhum período de atendimento.') }}</p>
            @else
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('Dia') }}</th>
                            <th>{{ __('Período') }}</th>
                            <th class="text-end">{{ __('Ações') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($horarios as $dia => $periodos)
                            @foreach ($periodos as $periodo)
                                <tr>
                                    <td>{{ $loop->first ? $dias[$dia] : '' }}</td>
                                    <td>{{ substr($periodo->hora_inicio, 0, 5) }}–{{ substr($periodo->hora_fim, 0, 5) }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('profissional.horario.destroy', $periodo) }}" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Remover') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="col-lg-5">
            <form method="POST" action="{{ route('profissional.horario.store') }}" class="border rounded p-3" novalidate>
                @csrf
                <h2 class="h5 mb-3">{{ __('Adicionar período') }}</h2>

                <div class="mb-3">
                    <label for="dia_semana" class="form-label">{{ __('Dia') }}</label>
                    <select id="dia_semana" name="dia_semana" class="form-select @error('dia_semana') is-invalid @enderror" required>
                        @foreach ($dias as $numero => $nome)
                            <option value="{{ $numero }}" @selected(old('dia_semana', 1) == $numero)>{{ $nome }}</option>
                        @endforeach
                    </select>
                    @error('dia_semana') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row g-2 mb-3">
                    <div class="col">
                        <label for="hora_inicio" class="form-label">{{ __('Hora de início') }}</label>
                        <input type="time" id="hora_inicio" name="hora_inicio" step="1800" required
                               class="form-control @error('hora_inicio') is-invalid @enderror" value="{{ old('hora_inicio', '09:00') }}">
                        @error('hora_inicio') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col">
                        <label for="hora_fim" class="form-label">{{ __('Hora de fim') }}</label>
                        <input type="time" id="hora_fim" name="hora_fim" step="1800" required
                               class="form-control @error('hora_fim') is-invalid @enderror" value="{{ old('hora_fim', '13:00') }}">
                        @error('hora_fim') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">{{ __('Adicionar') }}</button>
            </form>
        </div>
    </div>
@endsection
