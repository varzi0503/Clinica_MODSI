@extends('layouts.app')

@section('title', __('Consulta'))

@section('cabecalho')
    <div class="mc-head-row">
        <h1>{{ __('Consulta') }} · {{ $consulta->inicio->format('d/m/Y H:i') }}</h1>
        <a href="{{ route('profissional.agenda.index', ['data' => $consulta->inicio->toDateString()]) }}" class="btn btn-outline-secondary">{{ __('Voltar à agenda') }}</a>
    </div>
@endsection

@section('content')
    <div class="card mb-4">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">{{ __('Paciente') }}</dt>
                <dd class="col-sm-9"><a href="{{ route('profissional.pacientes.historico', $consulta->paciente) }}">{{ $consulta->paciente->nome }}</a> · {{ __('nº utente') }} {{ $consulta->paciente->num_utente }}</dd>
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
        </div>
    </div>

    <div class="row g-4">
        <section class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h5">{{ __('Observações') }}</h2>
                    <p class="small text-muted">{{ __('As observações são notas suas: o paciente e a receção não as veem.') }}</p>

                    @if ($consulta->aceitaObservacoes())
                        <form method="POST" action="{{ route('profissional.consultas.observar', $consulta) }}" class="mb-3" novalidate>
                            @csrf
                            <label for="texto" class="visually-hidden">{{ __('Nova observação') }}</label>
                            <textarea id="texto" name="texto" rows="3" maxlength="5000" required placeholder="{{ __('Nova observação') }}"
                                      class="form-control @error('texto') is-invalid @enderror">{{ old('texto') }}</textarea>
                            @error('texto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <button type="submit" class="btn btn-primary mt-2">{{ __('Guardar observação') }}</button>
                        </form>
                    @endif

                    @forelse ($consulta->observacoes as $observacao)
                        <div class="border rounded-3 p-3 mb-2 bg-body-tertiary">
                            <div class="small text-muted">{{ $observacao->created_at->format('d/m/Y H:i') }}</div>
                            <div style="white-space: pre-line">{{ $observacao->texto }}</div>
                        </div>
                    @empty
                        <p class="text-muted">{{ __('Ainda não há observações nesta consulta.') }}</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    @include('partials.documentos', ['consulta' => $consulta])
                </div>
            </div>
        </section>
    </div>
@endsection
