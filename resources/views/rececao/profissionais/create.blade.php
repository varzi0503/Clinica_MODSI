@extends('layouts.app')

@section('title', __('Novo profissional'))

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-7">
            <h1 class="h3 mb-2">{{ __('Novo profissional') }}</h1>
            <p class="text-muted small mb-4">{{ __('Cria a conta e a ficha de uma vez. O horário de atendimento é definido pelo próprio profissional, depois de entrar.') }}</p>

            @if ($especialidades->isEmpty())
                <div class="alert alert-warning">{{ __('Ainda não há especialidades. Crie pelo menos uma antes de criar profissionais.') }}</div>
            @endif

            <form method="POST" action="{{ route('rececao.profissionais.store') }}" novalidate>
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">{{ __('Nome') }}</label>
                        <input type="text" id="name" name="name" maxlength="255" required
                               class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label">{{ __('Email') }}</label>
                        <input type="email" id="email" name="email" maxlength="255" required
                               class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    @include('rececao.profissionais._ficha', ['profissional' => null])

                    <div class="col-md-6">
                        <label for="password" class="form-label">{{ __('Palavra-passe inicial') }}</label>
                        <input type="password" id="password" name="password" required autocomplete="new-password"
                               class="form-control @error('password') is-invalid @enderror">
                        <div class="form-text">{{ __('Pelo menos 8 caracteres.') }}</div>
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="password_confirmation" class="form-label">{{ __('Confirmar palavra-passe') }}</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" class="form-control">
                    </div>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">{{ __('Criar profissional') }}</button>
                    <a href="{{ route('rececao.profissionais.index') }}" class="btn btn-outline-secondary">{{ __('Cancelar') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection
