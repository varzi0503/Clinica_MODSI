<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocaleTest extends TestCase
{
    public function test_portugues_por_defeito(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Início')
            ->assertSee('lang="pt"', false);
    }

    public function test_escolher_idioma_guarda_na_sessao(): void
    {
        $this->post('/idioma/en')
            ->assertRedirect()
            ->assertSessionHas('locale', 'en');
    }

    public function test_pagina_em_ingles_quando_escolhido(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get('/')
            ->assertOk()
            ->assertSee('Home')
            ->assertDontSee('Início')
            ->assertSee('lang="en"', false);
    }

    public function test_idioma_nao_suportado_e_recusado(): void
    {
        $this->post('/idioma/fr')->assertNotFound();
    }
}
