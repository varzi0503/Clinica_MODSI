@extends('layouts.app')

@section('title', __('Estatísticas'))

@section('cabecalho')
    <div class="mc-head-row">
        <h1>{{ __('Estatísticas') }}</h1>
        <p class="mc-stat mb-0">
            <span>{{ __('Consultas no total') }}:</span>
            <strong data-total>{{ $total }}</strong>
        </p>
    </div>
@endsection

@section('content')
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end" novalidate>
                <div class="col-6 col-sm-4 col-md-3">
                    <label for="de" class="form-label">{{ __('De') }}</label>
                    <input type="date" id="de" name="de" class="form-control @error('de') is-invalid @enderror" value="{{ old('de', $filtros['de'] ?? '') }}">
                    @error('de') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <label for="ate" class="form-label">{{ __('Até') }}</label>
                    <input type="date" id="ate" name="ate" class="form-control @error('ate') is-invalid @enderror" value="{{ old('ate', $filtros['ate'] ?? '') }}">
                    @error('ate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary">{{ __('Filtrar') }}</button>
                    @if (($filtros['de'] ?? null) || ($filtros['ate'] ?? null))
                        <a href="{{ route('rececao.estatisticas.index') }}" class="btn btn-link">{{ __('Limpar') }}</a>
                    @endif
                </div>
            </form>
            <p class="text-muted small mt-3 mb-0">{{ __('O período aplica-se à data da consulta. Sem datas, contam todas as consultas.') }}</p>
        </div>
    </div>

    @if ($total === 0)
        <p class="text-muted">{{ __('Não há consultas neste período.') }}</p>
    @else
        <script type="application/json" id="dados-estatisticas">@json($graficos)</script>

        <section class="card card-body mb-4" aria-labelledby="titulo-estados">
            <h2 class="h5" id="titulo-estados">{{ __('Consultas por estado') }}</h2>
            <div class="row g-4 align-items-start">
                <div class="col-lg-7">
                    <div class="position-relative" style="height: 280px">
                        <canvas data-grafico="estados" role="img" aria-label="{{ __('Consultas por estado') }}"></canvas>
                    </div>
                </div>
                <div class="col-lg-5">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Estado') }}</th>
                                <th class="text-end">{{ __('Consultas') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($estados as $estado)
                                <tr>
                                    <td>
                                        <span class="d-inline-block rounded-1 me-1 align-middle" style="width: .75rem; height: .75rem; background: {{ $estado['cor'] }}" aria-hidden="true"></span>
                                        {{ $estado['nome'] }}
                                    </td>
                                    <td class="text-end">{{ $estado['total'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        @foreach ([
            ['chave' => 'profissionais', 'titulo' => __('Consultas por profissional'), 'coluna' => __('Profissional'), 'linhas' => $profissionais],
            ['chave' => 'especialidades', 'titulo' => __('Consultas por especialidade'), 'coluna' => __('Especialidade'), 'linhas' => $especialidades],
        ] as $bloco)
            <section class="card card-body mb-4" aria-labelledby="titulo-{{ $bloco['chave'] }}">
                <h2 class="h5" id="titulo-{{ $bloco['chave'] }}">{{ $bloco['titulo'] }}</h2>

                @if ($bloco['linhas']->where('total', '>', 0)->isNotEmpty())
                    <div class="position-relative mb-3" style="height: {{ 110 + 34 * $bloco['linhas']->where('total', '>', 0)->count() }}px">
                        <canvas data-grafico="{{ $bloco['chave'] }}" role="img" aria-label="{{ $bloco['titulo'] }}"></canvas>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ $bloco['coluna'] }}</th>
                                @foreach ($estados as $estado)
                                    <th class="text-end">{{ $estado['nome'] }}</th>
                                @endforeach
                                <th class="text-end">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bloco['linhas'] as $linha)
                                <tr>
                                    <td>
                                        {{ $linha['nome'] }}
                                        @isset($linha['tipo'])
                                            <span class="text-muted small">({{ \App\Enums\TipoProfissional::from($linha['tipo'])->label() }})</span>
                                        @endisset
                                    </td>
                                    @foreach ($estados as $estado)
                                        <td class="text-end">{{ $linha['estados'][$estado['valor']] }}</td>
                                    @endforeach
                                    <td class="text-end fw-semibold">{{ $linha['total'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    @endif
@endsection
