<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomeTest extends TestCase
{
    public function test_pagina_inicial_carrega(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(config('app.name'))
            ->assertSee('navbar-toggler', false);
    }
}
