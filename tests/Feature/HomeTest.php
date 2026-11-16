<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    // A página inicial lista as especialidades (#52), por isso precisa da base de dados.
    use RefreshDatabase;

    public function test_pagina_inicial_carrega(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(config('app.name'))
            ->assertSee('navbar-toggler', false);
    }
}
