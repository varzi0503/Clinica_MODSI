@extends('layouts.app')

@section('title', __('Início') . ' — ' . $clinica['nome'])

@section('content')
    <div class="p-4 p-md-5 mb-4 bg-body-tertiary rounded-3">
        <h1 class="display-6 fw-semibold">{{ __('Bem-vindo à :nome', ['nome' => $clinica['nome']]) }}</h1>
        <p class="lead text-body-secondary">{{ __('Marque e acompanhe as suas consultas online.') }}</p>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('disponibilidade.index') }}" class="btn btn-primary">{{ __('Ver disponibilidade') }}</a>
            @guest
                <a href="{{ route('registo') }}" class="btn btn-outline-primary">{{ __('Criar conta') }}</a>
                <a href="{{ route('login') }}" class="btn btn-outline-secondary">{{ __('Entrar') }}</a>
            @endguest
            @auth
                @if (auth()->user()->temPerfil('paciente'))
                    <a href="{{ route('paciente.pedidos.create') }}" class="btn btn-outline-primary">{{ __('Pedir consulta') }}</a>
                @endif
            @endauth
        </div>
    </div>

    <div class="row g-4">
        <section class="col-md-6 col-lg-4" aria-labelledby="titulo-contactos">
            <h2 class="h5" id="titulo-contactos">{{ __('Contactos') }}</h2>
            <address class="mb-0">
                {{ $clinica['morada'] }}<br>
                {{ __('Telefone') }}: <a href="tel:{{ preg_replace('/\s+/', '', $clinica['telefone']) }}">{{ $clinica['telefone'] }}</a><br>
                {{ __('Email') }}: <a href="mailto:{{ $clinica['email'] }}">{{ $clinica['email'] }}</a>
            </address>
        </section>

        <section class="col-md-6 col-lg-4" aria-labelledby="titulo-horario">
            <h2 class="h5" id="titulo-horario">{{ __('Horário') }}</h2>
            <p class="mb-1">{{ __('Todos os dias, das :abre às :fecha.', $clinica['horario']) }}</p>
            <p class="small text-muted mb-0">{{ __('O horário de cada profissional vê-se na disponibilidade.') }}</p>
        </section>

        <section class="col-lg-4" aria-labelledby="titulo-servicos">
            <h2 class="h5" id="titulo-servicos">{{ __('Serviços') }}</h2>
            @if ($especialidades->isEmpty())
                <p class="text-muted mb-0">{{ __('As especialidades serão anunciadas em breve.') }}</p>
            @else
                <ul class="list-unstyled mb-2">
                    @foreach ($especialidades->take(6) as $especialidade)
                        <li><a href="{{ route('profissionais.index', ['especialidade_id' => $especialidade->id]) }}">{{ $especialidade->nome }}</a></li>
                    @endforeach
                </ul>
                <a href="{{ route('especialidades.index') }}" class="small">{{ __('Ver todas as especialidades') }}</a> ·
                <a href="{{ route('profissionais.index') }}" class="small">{{ __('Ver os profissionais') }}</a>
            @endif
        </section>
    </div>
@endsection
