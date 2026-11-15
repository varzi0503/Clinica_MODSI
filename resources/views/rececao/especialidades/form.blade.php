@extends('layouts.app')

@php
    $titulo = $especialidade ? __('Editar especialidade') : __('Nova especialidade');
@endphp

@section('title', $titulo)

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <h1 class="h3 mb-4">{{ $titulo }}</h1>

            <form method="POST" novalidate
                  action="{{ $especialidade ? route('rececao.especialidades.update', $especialidade) : route('rececao.especialidades.store') }}">
                @csrf
                @if ($especialidade)
                    @method('PUT')
                @endif

                <div class="mb-3">
                    <label for="nome" class="form-label">{{ __('Nome') }}</label>
                    <input type="text" id="nome" name="nome" maxlength="100" required
                           class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $especialidade?->nome) }}">
                    @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-4">
                    <label for="descricao" class="form-label">{{ __('Descrição') }} <span class="text-muted small">({{ __('opcional') }})</span></label>
                    <textarea id="descricao" name="descricao" rows="3" maxlength="1000"
                              class="form-control @error('descricao') is-invalid @enderror">{{ old('descricao', $especialidade?->descricao) }}</textarea>
                    @error('descricao') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">{{ $especialidade ? __('Guardar') : __('Criar especialidade') }}</button>
                    <a href="{{ route('rececao.especialidades.index') }}" class="btn btn-outline-secondary">{{ __('Cancelar') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection
