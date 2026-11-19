@extends('layouts.app')

@section('title', __('Início') . ' — ' . $clinica['nome'])

@section('cabecalho-classe', 'mc-hero')

@section('cabecalho')
    <div class="row align-items-center g-4">
        <div class="col-lg-8">
            <h1>{{ __('Bem-vindo à :nome', ['nome' => $clinica['nome']]) }}</h1>
            <p class="lead mt-3">{{ __('Marque e acompanhe as suas consultas online.') }}</p>
            <div class="d-flex flex-wrap gap-2 mt-4">
                <a href="{{ route('disponibilidade.index') }}" class="btn btn-primary btn-lg">{{ __('Ver disponibilidade') }}</a>
                @guest
                    <a href="{{ route('registo') }}" class="btn btn-outline-primary btn-lg">{{ __('Criar conta') }}</a>
                    <a href="{{ route('login') }}" class="btn btn-outline-secondary btn-lg">{{ __('Entrar') }}</a>
                @endguest
                @auth
                    @if (auth()->user()->temPerfil('paciente'))
                        <a href="{{ route('paciente.pedidos.create') }}" class="btn btn-outline-primary btn-lg">{{ __('Pedir consulta') }}</a>
                    @endif
                @endauth
            </div>
        </div>
        {{-- Ilustração decorativa (só CSS/SVG, sem texto). --}}
        <div class="col-lg-4 d-none d-lg-block" aria-hidden="true">
            <svg viewBox="0 0 240 170" class="w-100" style="max-height: 11rem" fill="none">
                <circle cx="170" cy="85" r="78" stroke="rgba(255,255,255,.10)" stroke-width="2"/>
                <circle cx="170" cy="85" r="56" stroke="rgba(255,255,255,.14)" stroke-width="2"/>
                <rect x="138" y="52" width="64" height="64" rx="16" fill="rgba(255,255,255,.10)" stroke="rgba(255,255,255,.25)"/>
                <path d="M170 68v34M153 85h34" stroke="#5eead4" stroke-width="7" stroke-linecap="round"/>
                <rect x="10" y="40" width="112" height="46" rx="10" fill="#fff"/>
                <rect x="24" y="54" width="54" height="7" rx="3.5" fill="#0b3f4a"/>
                <rect x="24" y="68" width="78" height="6" rx="3" fill="#dfe6e9"/>
                <circle cx="108" cy="57" r="5" fill="#13844f"/>
                <rect x="40" y="100" width="76" height="28" rx="8" fill="rgba(255,255,255,.12)" stroke="rgba(255,255,255,.2)"/>
                <rect x="50" y="110" width="22" height="8" rx="4" fill="#5eead4"/>
                <rect x="78" y="110" width="26" height="8" rx="4" fill="rgba(255,255,255,.35)"/>
            </svg>
        </div>
    </div>
@endsection

@section('content')
    <div class="row g-4 mc-sobe">
        <section class="col-md-6 col-lg-4" aria-labelledby="titulo-contactos">
            <div class="card h-100">
                <div class="card-body">
                    <span class="mc-icon mb-3" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
                    </span>
                    <h2 class="h3" id="titulo-contactos">{{ __('Contactos') }}</h2>
                    <address class="mb-0">
                        {{ $clinica['morada'] }}<br>
                        {{ __('Telefone') }}: <a href="tel:{{ preg_replace('/\s+/', '', $clinica['telefone']) }}">{{ $clinica['telefone'] }}</a><br>
                        {{ __('Email') }}: <a href="mailto:{{ $clinica['email'] }}">{{ $clinica['email'] }}</a>
                    </address>
                </div>
            </div>
        </section>

        <section class="col-md-6 col-lg-4" aria-labelledby="titulo-horario">
            <div class="card h-100">
                <div class="card-body">
                    <span class="mc-icon mb-3" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    </span>
                    <h2 class="h3" id="titulo-horario">{{ __('Horário') }}</h2>
                    <p class="mb-1">{{ __('Todos os dias, das :abre às :fecha.', $clinica['horario']) }}</p>
                    <p class="small text-muted mb-0">{{ __('O horário de cada profissional vê-se na disponibilidade.') }}</p>
                </div>
            </div>
        </section>

        <section class="col-lg-4" aria-labelledby="titulo-servicos">
            <div class="card h-100">
                <div class="card-body">
                    <span class="mc-icon mb-3" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3v6a4 4 0 0 0 8 0V3"/><path d="M10 13v2a5 5 0 0 0 10 0v-2"/><circle cx="20" cy="11" r="2"/></svg>
                    </span>
                    <h2 class="h3" id="titulo-servicos">{{ __('Serviços') }}</h2>
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
                </div>
            </div>
        </section>
    </div>
@endsection
