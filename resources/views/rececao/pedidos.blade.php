@extends('layouts.app')

@section('title', __('Pedidos de consulta'))

@section('content')
    <h1 class="h3 mb-4">{{ __('Pedidos de consulta') }}</h1>

    <div data-pagina="rececao-pedidos" data-texto-erro="{{ __('Ocorreu um erro. Recarrega a página.') }}">
    <p class="text-muted {{ $pedidos->isEmpty() ? '' : 'd-none' }}" data-vazio>{{ __('Não há pedidos pendentes.') }}</p>

    @if ($pedidos->isNotEmpty())
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Pedido em') }}</th>
                        <th>{{ __('Paciente') }}</th>
                        <th>{{ __('Profissional') }}</th>
                        <th>{{ __('Especialidade') }}</th>
                        <th>{{ __('Consulta') }}</th>
                        <th>{{ __('Motivo da consulta') }}</th>
                        <th class="text-end">{{ __('Ações') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pedidos as $pedido)
                        <tr data-pedido="{{ $pedido->id }}">
                            <td>{{ $pedido->data_pedido->format('d/m/Y H:i') }}</td>
                            <td>{{ $pedido->paciente->nome }}</td>
                            <td>{{ $pedido->profissional->user->name }} <small class="text-muted">({{ $pedido->profissional->tipo->label() }})</small></td>
                            <td>{{ $pedido->especialidade->nome }}</td>
                            <td>{{ $pedido->inicio->format('d/m/Y H:i') }}</td>
                            <td>{{ $pedido->motivo_consulta }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('rececao.pedidos.confirmar', $pedido) }}" class="d-inline" data-ajax="pedido">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success">{{ __('Confirmar') }}</button>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="collapse" data-bs-target="#rejeitar-{{ $pedido->id }}">
                                    {{ __('Rejeitar') }}
                                </button>
                            </td>
                        </tr>
                        <tr class="collapse" id="rejeitar-{{ $pedido->id }}" data-pedido="{{ $pedido->id }}">
                            <td colspan="7">
                                <form method="POST" action="{{ route('rececao.pedidos.rejeitar', $pedido) }}" class="d-flex gap-2" data-ajax="pedido">
                                    @csrf
                                    <input type="text" name="motivo" class="form-control form-control-sm @error('motivo') is-invalid @enderror"
                                           placeholder="{{ __('Motivo da rejeição') }}" required maxlength="255">
                                    <button type="submit" class="btn btn-sm btn-danger text-nowrap">{{ __('Confirmar rejeição') }}</button>
                                </form>
                                @error('motivo')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    </div>
@endsection
