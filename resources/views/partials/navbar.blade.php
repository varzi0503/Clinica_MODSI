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
                    @if (auth()->user()->temPerfil('administrador'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.utilizadores.*') ? 'active' : '' }}"
                               href="{{ route('admin.utilizadores.index') }}">{{ __('Utilizadores') }}</a>
                        </li>
                    @endif
                    @if (auth()->user()->temPerfil('rececionista'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('rececao.pedidos.*') ? 'active' : '' }}"
                               href="{{ route('rececao.pedidos.index') }}">{{ __('Pedidos') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('rececao.consultas.create') ? 'active' : '' }}"
                               href="{{ route('rececao.consultas.create') }}">{{ __('Marcar consulta') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('rececao.consultas.*') && ! request()->routeIs('rececao.consultas.create') ? 'active' : '' }}"
                               href="{{ route('rececao.consultas.index') }}">{{ __('Consultas') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('rececao.agenda.*') ? 'active' : '' }}"
                               href="{{ route('rececao.agenda.index') }}">{{ __('Agenda geral') }}</a>
                        </li>
                        @php $emGestao = request()->routeIs('rececao.pacientes.*', 'rececao.profissionais.*', 'rececao.especialidades.*', 'rececao.estatisticas.*'); @endphp
                        <li class="nav-item dropdown">
                            <button class="nav-link dropdown-toggle {{ $emGestao ? 'active' : '' }}" type="button"
                                    data-bs-toggle="dropdown" aria-expanded="false">{{ __('Gestão') }}</button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item {{ request()->routeIs('rececao.pacientes.*') ? 'active' : '' }}"
                                       href="{{ route('rececao.pacientes.index') }}">{{ __('Pacientes') }}</a></li>
                                <li><a class="dropdown-item {{ request()->routeIs('rececao.profissionais.*') ? 'active' : '' }}"
                                       href="{{ route('rececao.profissionais.index') }}">{{ __('Profissionais') }}</a></li>
                                <li><a class="dropdown-item {{ request()->routeIs('rececao.especialidades.*') ? 'active' : '' }}"
                                       href="{{ route('rececao.especialidades.index') }}">{{ __('Especialidades') }}</a></li>
                                <li><a class="dropdown-item {{ request()->routeIs('rececao.estatisticas.*') ? 'active' : '' }}"
                                       href="{{ route('rececao.estatisticas.index') }}">{{ __('Estatísticas') }}</a></li>
                            </ul>
                        </li>
                    @endif
                    @if (auth()->user()->temPerfil('paciente'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('paciente.pedidos.*') ? 'active' : '' }}"
                               href="{{ route('paciente.pedidos.create') }}">{{ __('Pedir consulta') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('paciente.consultas.*') ? 'active' : '' }}"
                               href="{{ route('paciente.consultas.index') }}">{{ __('As minhas consultas') }}</a>
                        </li>
                    @endif
                    @if (auth()->user()->temPerfil('profissional'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('profissional.agenda.*') ? 'active' : '' }}"
                               href="{{ route('profissional.agenda.index') }}">{{ __('Agenda') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('profissional.horario.*') ? 'active' : '' }}"
                               href="{{ route('profissional.horario.index') }}">{{ __('O meu horário') }}</a>
                        </li>
                    @endif
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
                    @php $porLer = auth()->user()->unreadNotifications()->count(); @endphp
                    <li class="nav-item d-flex align-items-center">
                        <a class="nav-link {{ request()->routeIs('notificacoes.*') ? 'active' : '' }}" href="{{ route('notificacoes.index') }}">
                            {{ __('Notificações') }}
                            @if ($porLer > 0)
                                <span class="badge rounded-pill text-bg-danger" data-por-ler>{{ $porLer }}</span>
                                <span class="visually-hidden">{{ __('por ler') }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item d-flex align-items-center">
                        <a class="nav-link {{ request()->routeIs('dados-pessoais.*') ? 'active' : '' }}"
                           href="{{ route('dados-pessoais.edit') }}" title="{{ __('Dados Pessoais') }}">{{ auth()->user()->name }}</a>
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
