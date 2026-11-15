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

                    <div class="col-md-4">
                        <label for="tipo" class="form-label">{{ __('Tipo') }}</label>
                        <select id="tipo" name="tipo" required class="form-select @error('tipo') is-invalid @enderror">
                            @foreach ($tipos as $tipo)
                                <option value="{{ $tipo->value }}" @selected(old('tipo') === $tipo->value)>{{ $tipo->label() }}</option>
                            @endforeach
                        </select>
                        @error('tipo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="num_cedula" class="form-label">{{ __('Nº de cédula') }}</label>
                        <input type="text" id="num_cedula" name="num_cedula" maxlength="20" required
                               class="form-control @error('num_cedula') is-invalid @enderror" value="{{ old('num_cedula') }}">
                        @error('num_cedula') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="contacto" class="form-label">{{ __('Contacto') }}</label>
                        <input type="tel" id="contacto" name="contacto" maxlength="20"
                               class="form-control @error('contacto') is-invalid @enderror" value="{{ old('contacto') }}">
                        @error('contacto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <fieldset>
                            <legend class="form-label fs-6">{{ __('Especialidades') }}</legend>
                            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-1">
                                @foreach ($especialidades as $especialidade)
                                    <div class="col">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input @error('especialidades') is-invalid @enderror"
                                                   id="especialidade-{{ $especialidade->id }}" name="especialidades[]" value="{{ $especialidade->id }}"
                                                   @checked(in_array($especialidade->id, array_map('intval', (array) old('especialidades', [])), true))>
                                            <label class="form-check-label" for="especialidade-{{ $especialidade->id }}">{{ $especialidade->nome }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @error('especialidades') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            @error('especialidades.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </fieldset>
                    </div>

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
