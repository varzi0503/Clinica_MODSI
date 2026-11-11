@extends('layouts.app')

@section('title', __('Criar conta'))

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-5">
            <h1 class="h3 mb-4 text-center">{{ __('Criar conta') }}</h1>

            <form method="POST" action="{{ route('registo.store') }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="nome" class="form-label">{{ __('Nome completo') }}</label>
                    <input type="text" name="nome" id="nome" maxlength="255" required autofocus autocomplete="name"
                           class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome') }}">
                    @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">{{ __('Email') }}</label>
                    <input type="email" name="email" id="email" maxlength="255" required autocomplete="email"
                           class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label for="num_utente" class="form-label">{{ __('Nº de utente') }}</label>
                        <input type="text" name="num_utente" id="num_utente" inputmode="numeric" maxlength="9" required
                               class="form-control @error('num_utente') is-invalid @enderror" value="{{ old('num_utente') }}">
                        @error('num_utente') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-sm-6">
                        <label for="telefone" class="form-label">{{ __('Telefone') }} <small class="text-muted">({{ __('opcional') }})</small></label>
                        <input type="tel" name="telefone" id="telefone" maxlength="20" autocomplete="tel"
                               class="form-control @error('telefone') is-invalid @enderror" value="{{ old('telefone') }}">
                        @error('telefone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">{{ __('Palavra-passe') }}</label>
                    <input type="password" name="password" id="password" required autocomplete="new-password"
                           class="form-control @error('password') is-invalid @enderror">
                    <div class="form-text">{{ __('Pelo menos 8 caracteres.') }}</div>
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-4">
                    <label for="password_confirmation" class="form-label">{{ __('Confirmar palavra-passe') }}</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password"
                           class="form-control">
                </div>

                <button type="submit" class="btn btn-primary w-100">{{ __('Criar conta') }}</button>

                <p class="text-center mt-3 mb-0 small">
                    {{ __('Já tem conta?') }} <a href="{{ route('login') }}">{{ __('Entrar') }}</a>
                </p>
            </form>
        </div>
    </div>
@endsection
