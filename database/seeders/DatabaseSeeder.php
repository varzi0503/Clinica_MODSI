<?php

namespace Database\Seeders;

use App\Models\Perfil;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Os três perfis fixos da aplicação. Os dados de demonstração
        // (profissionais, horários, pacientes, consultas) ficam em seeders próprios.
        $perfis = collect(['paciente', 'rececionista', 'profissional'])
            ->mapWithKeys(fn (string $nome) => [$nome => Perfil::firstOrCreate(['nome' => $nome])]);

        User::factory()->create([
            'perfil_id' => $perfis['rececionista']->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
