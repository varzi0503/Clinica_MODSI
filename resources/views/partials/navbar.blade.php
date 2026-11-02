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
            </ul>
        </div>
    </div>
</nav>
