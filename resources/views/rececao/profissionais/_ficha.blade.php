{{-- Campos da ficha, comuns a criar e a editar. $profissional é nulo ao criar. --}}
@php
    $escolhidas = array_map('intval', (array) old('especialidades', $profissional?->especialidades->pluck('id')->all() ?? []));
@endphp

<div class="col-md-4">
    <label for="tipo" class="form-label">{{ __('Tipo') }}</label>
    <select id="tipo" name="tipo" required class="form-select @error('tipo') is-invalid @enderror">
        @foreach ($tipos as $tipo)
            <option value="{{ $tipo->value }}" @selected(old('tipo', $profissional?->tipo->value) === $tipo->value)>{{ $tipo->label() }}</option>
        @endforeach
    </select>
    @error('tipo') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="col-md-4">
    <label for="num_cedula" class="form-label">{{ __('Nº de cédula') }}</label>
    <input type="text" id="num_cedula" name="num_cedula" maxlength="20" required
           class="form-control @error('num_cedula') is-invalid @enderror" value="{{ old('num_cedula', $profissional?->num_cedula) }}">
    @error('num_cedula') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="col-md-4">
    <label for="contacto" class="form-label">{{ __('Contacto') }}</label>
    <input type="tel" id="contacto" name="contacto" maxlength="20"
           class="form-control @error('contacto') is-invalid @enderror" value="{{ old('contacto', $profissional?->contacto) }}">
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
                               @checked(in_array($especialidade->id, $escolhidas, true))>
                        <label class="form-check-label" for="especialidade-{{ $especialidade->id }}">{{ $especialidade->nome }}</label>
                    </div>
                </div>
            @endforeach
        </div>
        @error('especialidades') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        @error('especialidades.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </fieldset>
</div>
