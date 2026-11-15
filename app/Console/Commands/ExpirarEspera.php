<?php

namespace App\Console\Commands;

use App\Services\Marcacoes;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * D33: os pedidos em espera cuja vaga já passou nunca vão ser atendidos. Este comando
 * passa-os a "cancelada". Corre-se à mão; não depende do agendador.
 */
#[Signature('consultas:expirar-espera')]
#[Description('Cancela os pedidos em lista de espera cuja vaga já passou')]
class ExpirarEspera extends Command
{
    public function handle(Marcacoes $marcacoes): int
    {
        $cancelados = $marcacoes->expirarEspera();

        $this->info("Pedidos em espera cancelados: {$cancelados}");

        return self::SUCCESS;
    }
}
