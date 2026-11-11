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
        // Os perfis fixos da aplicação: o acesso está ligado ao nome (sem tabela de permissões).
        // Os dados de demonstração (profissionais, horários, pacientes, consultas) ficam em seeders próprios.
        $perfis = collect(Perfil::NOMES)
            ->mapWithKeys(fn (string $nome) => [$nome => Perfil::firstOrCreate(['nome' => $nome])]);

        User::factory()->create([
            'perfil_id' => $perfis['administrador']->id,
            'name' => 'Administrador',
            'email' => 'admin@example.com',
        ]);

        User::factory()->create([
            'perfil_id' => $perfis['rececionista']->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
