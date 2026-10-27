<?php

use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

// POST (e não GET) porque altera o estado da sessão; o formulário leva token CSRF.
Route::post('/idioma/{locale}', LocaleController::class)
    ->whereIn('locale', array_keys(config('app.available_locales')))
    ->name('locale.update');
