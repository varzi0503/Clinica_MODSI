<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Guarda o idioma escolhido na sessão e volta à página onde o utilizador estava.
     * Os idiomas válidos são filtrados na rota (whereIn), por isso aqui já chega um valor válido.
     */
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        $request->session()->put('locale', $locale);

        return back();
    }
}
