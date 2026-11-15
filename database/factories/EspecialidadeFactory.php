<?php

namespace Database\Factories;

use App\Models\Especialidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Especialidade>
 */
class EspecialidadeFactory extends Factory
{
    private const NOMES = [
        'Cardiologia', 'Dermatologia', 'Pediatria', 'Ortopedia', 'Oftalmologia', 'Ginecologia',
        'Neurologia', 'Psiquiatria', 'Medicina Geral e Familiar', 'Enfermagem Geral',
        'Enfermagem de Reabilitação', 'Otorrinolaringologia', 'Urologia', 'Endocrinologia',
    ];

    /** Quantas especialidades esta factory já gerou: garante nomes únicos sem repetir a lista. */
    private static int $geradas = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fn (): string => self::proximoNome(),
            'descricao' => null,
        ];
    }

    /** Percorre a lista e, quando ela acaba, repete-a com um número ("Cardiologia 2"). */
    private static function proximoNome(): string
    {
        $indice = self::$geradas++;
        $volta = intdiv($indice, count(self::NOMES));
        $nome = self::NOMES[$indice % count(self::NOMES)];

        return $volta === 0 ? $nome : $nome.' '.($volta + 1);
    }
}
