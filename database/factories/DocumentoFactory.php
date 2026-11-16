<?php

namespace Database\Factories;

use App\Models\Consulta;
use App\Models\Documento;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Cria só o registo; não escreve nenhum ficheiro no disco.
 *
 * @extends Factory<Documento>
 */
class DocumentoFactory extends Factory
{
    /**
     * Por omissão, um PDF carregado pelo profissional da consulta.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consulta_id' => Consulta::factory()->confirmada(),
            'user_id' => fn (array $atributos): int => Consulta::findOrFail($atributos['consulta_id'])->profissional->user_id,
            'nome_original' => fake()->word().'.pdf',
            'caminho' => fn (array $atributos): string => "documentos/{$atributos['consulta_id']}/".Str::random(40).'.pdf',
            'mime' => 'application/pdf',
            'tamanho' => fake()->numberBetween(20_000, 900_000),
        ];
    }

    /** Carregado pelo paciente da consulta (por exemplo, análises). A ficha tem de ter conta. */
    public function doPaciente(): static
    {
        // O valor é uma closure para só ser calculado depois de a consulta existir.
        return $this->state([
            'user_id' => fn (array $atributos): int => Consulta::findOrFail($atributos['consulta_id'])->paciente->user_id,
        ]);
    }
}
