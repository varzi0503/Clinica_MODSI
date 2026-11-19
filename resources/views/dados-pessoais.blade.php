@extends('layouts.app')

@section('title', __('Dados Pessoais'))

@section('cabecalho')
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-7">
            <h1>{{ __('Dados Pessoais') }}</h1>
        </div>
    </div>
@endsection

@section('content')
    <div class="row justify-content-center mc-sobe">
        <div class="col-md-10 col-lg-7">
            <div class="card">
                <div class="card-body">
                    <dl class="row mb-4">
                        <dt class="col-sm-4">{{ __('Email') }}</dt>
                        <dd class="col-sm-8">{{ $user->email }}</dd>
                        <dt class="col-sm-4">{{ __('Perfil') }}</dt>
                        <dd class="col-sm-8">{{ __(ucfirst($user->perfil->nome)) }}</dd>
                        @if ($paciente)
                            <dt class="col-sm-4">{{ __('Nº de utente') }}</dt>
                            <dd class="col-sm-8">{{ $paciente->num_utente }}</dd>
                        @else
                            <dt class="col-sm-4">{{ __('Nome') }}</dt>
                            <dd class="col-sm-8">{{ $user->name }}</dd>
                        @endif
                    </dl>

                    @if ($paciente)
                        <h2 class="h5">{{ __('Nome e contactos') }}</h2>
                        <form method="POST" action="{{ route('dados-pessoais.update') }}" class="mb-5" novalidate>
                            @csrf
                            @method('PUT')

                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="nome" class="form-label">{{ __('Nome') }}</label>
                                    <input type="text" id="nome" name="nome" maxlength="255" required autocomplete="name"
                                           class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $paciente->nome) }}">
                                    @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-5">
                                    <label for="telefone" class="form-label">{{ __('Telefone') }} <span class="text-muted small">({{ __('opcional') }})</span></label>
                                    <input type="tel" id="telefone" name="telefone" maxlength="20" autocomplete="tel"
                                           class="form-control @error('telefone') is-invalid @enderror" value="{{ old('telefone', $paciente->telefone) }}">
                                    @error('telefone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-7">
                                    <label for="email_contacto" class="form-label">{{ __('Email de contacto') }} <span class="text-muted small">({{ __('opcional') }})</span></label>
                                    <input type="email" id="email_contacto" name="email_contacto" maxlength="255"
                                           class="form-control @error('email_contacto') is-invalid @enderror" value="{{ old('email_contacto', $paciente->email_contacto) }}">
                                    @error('email_contacto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <p class="form-text">{{ __('Para alterar o email de entrada ou o nº de utente, contacte a receção.') }}</p>
                            <button type="submit" class="btn btn-primary">{{ __('Guardar') }}</button>
                        </form>
                    @endif

                    <h2 class="h5">{{ __('Palavra-passe') }}</h2>
                    <form method="POST" action="{{ route('dados-pessoais.palavra-passe') }}" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="current_password" class="form-label">{{ __('Palavra-passe atual') }}</label>
                                <input type="password" id="current_password" name="current_password" required autocomplete="current-password"
                                       class="form-control @error('current_password', 'palavraPasse') is-invalid @enderror">
                                @error('current_password', 'palavraPasse') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="w-100 d-none d-md-block m-0"></div>
                            <div class="col-md-6">
                                <label for="password" class="form-label">{{ __('Nova palavra-passe') }}</label>
                                <input type="password" id="password" name="password" required autocomplete="new-password"
                                       class="form-control @error('password', 'palavraPasse') is-invalid @enderror">
                                <div class="form-text">{{ __('Pelo menos 8 caracteres.') }}</div>
                                @error('password', 'palavraPasse') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="password_confirmation" class="form-label">{{ __('Confirmar palavra-passe') }}</label>
                                <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" class="form-control">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary mt-3">{{ __('Alterar palavra-passe') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
