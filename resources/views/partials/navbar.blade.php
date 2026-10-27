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
                {{-- Especialidades e Médicos: entram com as páginas públicas (UC01–UC03) --}}
                {{-- Menus por perfil: entram com o controlo de perfis --}}
            </ul>

            <ul class="navbar-nav">
                {{-- Entrar / Registar / Sair: entram com o registo e o login (UC04, UC05) --}}

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
