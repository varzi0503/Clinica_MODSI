{{-- Documentos de uma consulta: lista e carregamento. Recebe $consulta (com documentos.autor carregados). --}}
<h2 class="h5">{{ __('Documentos') }}</h2>

@if ($consulta->documentos->isEmpty())
    <p class="text-muted">{{ __('Ainda não há documentos nesta consulta.') }}</p>
@else
    <ul class="list-group mb-3">
        @foreach ($consulta->documentos as $documento)
            <li class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <a href="{{ route('documentos.show', $documento) }}">{{ $documento->nome_original }}</a>
                    <div class="small text-muted">
                        {{ $documento->tamanhoLegivel() }} · {{ $documento->created_at->format('d/m/Y H:i') }} ·
                        {{ $documento->user_id === auth()->id() ? __('carregado por si') : __('carregado por :nome', ['nome' => $documento->autor->name]) }}
                    </div>
                </div>
                @can('apagar', $documento)
                    <form method="POST" action="{{ route('documentos.destroy', $documento) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Remover') }}</button>
                    </form>
                @endcan
            </li>
        @endforeach
    </ul>
@endif

@if ($consulta->aceitaDocumentos())
    <form method="POST" action="{{ route('documentos.store', $consulta) }}" enctype="multipart/form-data" class="row g-2 align-items-start" novalidate>
        @csrf
        <div class="col-sm-8">
            <label for="ficheiro" class="visually-hidden">{{ __('Ficheiro') }}</label>
            <input type="file" id="ficheiro" name="ficheiro" required accept=".pdf,.jpg,.jpeg,.png"
                   class="form-control @error('ficheiro') is-invalid @enderror">
            <div class="form-text">{{ __('PDF, JPG ou PNG, até 5 MB.') }}</div>
            @error('ficheiro') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-primary">{{ __('Carregar documento') }}</button>
        </div>
    </form>
@endif
