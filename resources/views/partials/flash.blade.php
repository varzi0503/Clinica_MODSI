@foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning'] as $chave => $cor)
    @if (session($chave))
        <div class="alert alert-{{ $cor }} alert-dismissible fade show" role="alert">
            {{ session($chave) }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Fechar') }}"></button>
        </div>
    @endif
@endforeach
