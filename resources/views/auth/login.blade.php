@extends('layouts.app')

@section('title', __('Entrar'))

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <h1 class="h3 mb-4 text-center">{{ __('Entrar') }}</h1>

            <form method="POST" action="{{ route('login.store') }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label">{{ __('Email') }}</label>
                    <input type="email" name="email" id="email"
                           class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email') }}" required autofocus autocomplete="username">
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">{{ __('Palavra-passe') }}</label>
                    <input type="password" name="password" id="password"
                           class="form-control @error('password') is-invalid @enderror"
                           required autocomplete="current-password">
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary w-100">{{ __('Entrar') }}</button>

                <p class="text-center mt-3 mb-0 small">
                    {{ __('Ainda não tem conta?') }} <a href="{{ route('registo') }}">{{ __('Criar conta') }}</a>
                </p>
            </form>
        </div>
    </div>
@endsection
