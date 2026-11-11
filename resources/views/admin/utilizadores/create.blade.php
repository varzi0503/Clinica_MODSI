@extends('layouts.app')

@section('title', __('Novo utilizador'))

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-5">
            <h1 class="h3 mb-2">{{ __('Novo utilizador') }}</h1>
            <p class="text-muted small mb-4">{{ __('Contas de profissionais são criadas pela receção, com a respetiva ficha. Contas de pacientes vêm do registo ou da receção.') }}</p>

            <form method="POST" action="{{ route('admin.utilizadores.store') }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="name" class="form-label">{{ __('Nome') }}</label>
                    <input type="text" id="name" name="name" maxlength="255" required
                           class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}">
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">{{ __('Email') }}</label>
                    <input type="email" id="email" name="email" maxlength="255" required
                           class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="perfil_id" class="form-label">{{ __('Perfil') }}</label>
                    <select id="perfil_id" name="perfil_id" required class="form-select @error('perfil_id') is-invalid @enderror">
                        @foreach ($perfis as $perfil)
                            <option value="{{ $perfil->id }}" @selected(old('perfil_id') == $perfil->id)>{{ __(ucfirst($perfil->nome)) }}</option>
                        @endforeach
                    </select>
                    @error('perfil_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">{{ __('Palavra-passe inicial') }}</label>
                    <input type="password" id="password" name="password" required autocomplete="new-password"
                           class="form-control @error('password') is-invalid @enderror">
                    <div class="form-text">{{ __('Pelo menos 8 caracteres.') }}</div>
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-4">
                    <label for="password_confirmation" class="form-label">{{ __('Confirmar palavra-passe') }}</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" class="form-control">
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">{{ __('Criar utilizador') }}</button>
                    <a href="{{ route('admin.utilizadores.index') }}" class="btn btn-outline-secondary">{{ __('Cancelar') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection
