@extends('layouts.app')

@php
    $titulo = $paciente ? __('Editar ficha') : __('Nova ficha');
@endphp

@section('title', $titulo)

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-7">
            <h1 class="h3 mb-4">{{ $titulo }}</h1>

            <form method="POST" novalidate
                  action="{{ $paciente ? route('rececao.pacientes.update', $paciente) : route('rececao.pacientes.store') }}">
                @csrf
                @if ($paciente)
                    @method('PUT')
                @endif

                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="nome" class="form-label">{{ __('Nome') }}</label>
                        <input type="text" id="nome" name="nome" maxlength="255" required
                               class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $paciente?->nome) }}">
                        @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="num_utente" class="form-label">{{ __('Nº de utente') }}</label>
                        <input type="text" id="num_utente" name="num_utente" inputmode="numeric" maxlength="9" required
                               class="form-control @error('num_utente') is-invalid @enderror" value="{{ old('num_utente', $paciente?->num_utente) }}">
                        @error('num_utente') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-5">
                        <label for="telefone" class="form-label">{{ __('Telefone') }} <span class="text-muted small">({{ __('opcional') }})</span></label>
                        <input type="tel" id="telefone" name="telefone" maxlength="20"
                               class="form-control @error('telefone') is-invalid @enderror" value="{{ old('telefone', $paciente?->telefone) }}">
                        @error('telefone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-7">
                        <label for="email_contacto" class="form-label">{{ __('Email de contacto') }} <span class="text-muted small">({{ __('opcional') }})</span></label>
                        <input type="email" id="email_contacto" name="email_contacto" maxlength="255"
                               class="form-control @error('email_contacto') is-invalid @enderror" value="{{ old('email_contacto', $paciente?->email_contacto) }}">
                        @error('email_contacto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">{{ $paciente ? __('Guardar') : __('Criar ficha') }}</button>
                    <a href="{{ route('rececao.pacientes.index') }}" class="btn btn-outline-secondary">{{ __('Cancelar') }}</a>
                </div>
            </form>

            @if ($paciente)
                <hr class="my-4">
                <h2 class="h5">{{ __('Conta') }}</h2>

                @if ($paciente->user)
                    <p class="mb-0">{{ __('Esta ficha está associada à conta :email.', ['email' => $paciente->user->email]) }}</p>
                @else
                    <p class="text-muted small">{{ __('Esta ficha não tem conta. Se o paciente já se registou, indique o email da conta para as associar. Confirme primeiro a identidade da pessoa.') }}</p>
                    <form method="POST" action="{{ route('rececao.pacientes.conta', $paciente) }}" class="row g-2 align-items-start" novalidate>
                        @csrf
                        <div class="col-sm-8">
                            <label for="email" class="visually-hidden">{{ __('Email') }}</label>
                            <input type="email" id="email" name="email" maxlength="255" required placeholder="{{ __('Email da conta') }}"
                                   class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-outline-primary">{{ __('Associar conta') }}</button>
                        </div>
                    </form>
                @endif
            @endif
        </div>
    </div>
@endsection
