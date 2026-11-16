@extends('layouts.app')

@section('title', __('Notificações'))

@section('content')
    <h1 class="h3 mb-4">{{ __('Notificações') }}</h1>

    @if ($notificacoes->isEmpty())
        <p class="text-muted">{{ __('Não tem notificações.') }}</p>
    @else
        <ul class="list-group mb-3">
            @foreach ($notificacoes as $notificacao)
                @php $nova = in_array($notificacao->id, $porLer, true); @endphp
                <li class="list-group-item {{ $nova ? 'list-group-item-primary' : '' }}">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <strong>
                            {{ \App\Notifications\ConsultaAtualizada::titulo($notificacao->data) }}
                            @if ($nova) <span class="badge text-bg-primary">{{ __('Nova') }}</span> @endif
                        </strong>
                        <small class="text-muted">{{ $notificacao->created_at->format('d/m/Y H:i') }}</small>
                    </div>
                    <div>{{ \App\Notifications\ConsultaAtualizada::texto($notificacao->data) }}</div>
                    @if ($notificacao->data['motivo'] ?? null)
                        <div class="small text-muted">{{ __('Motivo') }}: {{ $notificacao->data['motivo'] }}</div>
                    @endif
                </li>
            @endforeach
        </ul>

        {{ $notificacoes->links() }}
    @endif
@endsection
