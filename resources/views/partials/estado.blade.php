{{-- Etiqueta com o estado de uma consulta. Recebe $estado (App\Enums\EstadoConsulta). --}}
@php
    $coresDoEstado = [
        'em_espera' => 'text-bg-info',
        'pedida' => 'text-bg-warning',
        'confirmada' => 'text-bg-primary',
        'rejeitada' => 'text-bg-danger',
        'cancelada' => 'text-bg-dark',
        'realizada' => 'text-bg-success',
        'faltou' => 'text-bg-secondary',
    ];
@endphp
<span class="badge {{ $coresDoEstado[$estado->value] }}">{{ $estado->label() }}</span>
