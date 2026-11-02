@extends('layouts.app')

@section('title', __('Início') . ' — ' . config('app.name'))

@section('content')
    <h1 class="h3">{{ __('Bem-vindo à :nome', ['nome' => config('app.name')]) }}</h1>
    <p class="text-body-secondary">{{ __('Marque e acompanhe as suas consultas online.') }}</p>
@endsection
