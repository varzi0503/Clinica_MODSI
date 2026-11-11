<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="{{ route('home') }}">{{ config('app.name') }}</a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#nav-principal"
                aria-controls="nav-principal" aria-expanded="false" aria-label="{{ __('Abrir menu') }}">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="nav-principal">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}"
                       @if (request()->routeIs('home')) aria-current="page" @endif
                       href="{{ route('home') }}">{{ __('Início') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('disponibilidade.*') ? 'active' : '' }}"
                       href="{{ route('disponibilidade.index') }}">{{ __('Disponibilidade') }}</a>
                </li>
                {{-- Especialidades e Médicos: entram com as páginas públicas (UC01–UC03) --}}

                @auth
                    @if (auth()->user()->temPerfil('rececionista'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('rececao.pedidos.*') ? 'active' : '' }}"
                               href="{{ route('rececao.pedidos.index') }}">{{ __('Pedidos') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('rececao.consultas.*') ? 'active' : '' }}"
                               href="{{ route('rececao.consultas.create') }}">{{ __('Marcar consulta') }}</a>
                        </li>
                    @endif
                    {{-- Menus dos restantes perfis: entram com os UC respetivos --}}
                @endauth
            </ul>

            <ul class="navbar-nav">
                @guest
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('login') ? 'active' : '' }}"
                           href="{{ route('login') }}">{{ __('Entrar') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('registo') ? 'active' : '' }}"
                           href="{{ route('registo') }}">{{ __('Criar conta') }}</a>
                    </li>
                @endguest

                @auth
                    <li class="nav-item d-flex align-items-center">
                        <span class="navbar-text me-2">{{ auth()->user()->name }}</span>
                    </li>
                    <li class="nav-item d-flex align-items-center">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-link nav-link">{{ __('Sair') }}</button>
                        </form>
                    </li>
                @endauth

                <li class="nav-item dropdown">
                    <button class="nav-link dropdown-toggle" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Idioma') }}">
                        {{ strtoupper(app()->getLocale()) }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @foreach (config('app.available_locales') as $codigo => $nome)
                            <li>
                                <form method="POST" action="{{ route('locale.update', $codigo) }}">
                                    @csrf
                                    <button type="submit" lang="{{ $codigo }}"
                                            class="dropdown-item {{ app()->getLocale() === $codigo ? 'active' : '' }}">
                                        {{ $nome }}
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
