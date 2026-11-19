@extends('layouts.app')

@section('title', __('Editar profissional'))

@section('cabecalho')
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-7">
            <h1>{{ __('Editar profissional') }}</h1>
            <p class="mt-2 mb-0">{{ $profissional->user->email }}</p>
        </div>
    </div>
@endsection

@section('content')
    <div class="row justify-content-center mc-sobe">
        <div class="col-md-10 col-lg-7">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('rececao.profissionais.update', $profissional) }}" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="name" class="form-label">{{ __('Nome') }}</label>
                                <input type="text" id="name" name="name" maxlength="255" required
                                       class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $profissional->user->name) }}">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            @include('rececao.profissionais._ficha', ['profissional' => $profissional])
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">{{ __('Guardar') }}</button>
                            <a href="{{ route('rececao.profissionais.index') }}" class="btn btn-outline-secondary">{{ __('Cancelar') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
