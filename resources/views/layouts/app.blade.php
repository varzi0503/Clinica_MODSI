<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    {{-- Tipos de letra do tema (D43). Sem internet, o tema usa os do sistema. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="d-flex flex-column min-vh-100">
    @include('partials.navbar')

    <main class="flex-grow-1">
        <div class="container">
            {{-- Título da página sobre a banda de cor (D43). O bloco seguinte sobe sobre ela se for um cartão, uma tabela ou .mc-sobe. --}}
            @hasSection('cabecalho')
                <header class="mc-page-head @yield('cabecalho-classe')">
                    @yield('cabecalho')
                </header>
            @endif
            @include('partials.flash')
            @yield('content')
        </div>
    </main>

    @include('partials.footer')
</body>
</html>
