<?php

namespace Database\Seeders;

use App\Models\Consulta;
use App\Models\Especialidade;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $rececionista = User::where('email', 'test@example.com')->firstOrFail();

        // Especialidades
        $cardio = Especialidade::factory()->create(['nome' => 'Cardiologia']);
        $pediatria = Especialidade::factory()->create(['nome' => 'Pediatria']);
        $derma = Especialidade::factory()->create(['nome' => 'Dermatologia']);
        $mgf = Especialidade::factory()->create(['nome' => 'Medicina Geral e Familiar']);
        $enf = Especialidade::factory()->create(['nome' => 'Enfermagem Geral']);

        // Profissionais (horários seg-sex; 1 = segunda ... 7 = domingo)
        $medica = Profissional::factory()->medico()
            ->for(User::factory()->profissional()->state(['email' => 'medica@example.com', 'name' => 'Ana Silva']))
            ->comEspecialidade($cardio)->comEspecialidade($mgf)
            ->comHorario(1, '09:00', '13:00')->comHorario(3, '14:00', '18:00')->comHorario(5, '09:00', '13:00')
            ->create();

        $pediatra = Profissional::factory()->medico()
            ->comEspecialidade($pediatria)
            ->comHorario(2, '09:00', '13:00')->comHorario(4, '09:00', '13:00')
            ->create();

        $dermato = Profissional::factory()->medico()
            ->comEspecialidade($derma)
            ->comHorario(1, '14:00', '18:00')->comHorario(4, '14:00', '18:00')
            ->create();

        $enfermeiro = Profissional::factory()->enfermeiro()
            ->comEspecialidade($enf)
            ->comHorario(2, '09:00', '17:00')->comHorario(3, '09:00', '17:00')->comHorario(5, '09:00', '17:00')
            ->create();

        // Pacientes
        $principal = Paciente::factory()->create();
        $principal->user->forceFill(['email' => 'paciente@example.com'])->save();
        $pacientes = Paciente::factory()->count(10)->create();
        $balcao = Paciente::factory()->semConta()->count(4)->create();
        $todos = $pacientes->concat($balcao)->prepend($principal)->values(); // 15

        // Datas relativas a hoje. Hora certa/meia, dentro do horário, e sem colisões.
        // Proxima segunda/quarta/etc.: ajusta com next()/startOfWeek conforme o dia.
        $segunda = now()->next('Monday')->setTime(9, 0);      // medica seg 09-13
        $quarta = now()->next('Wednesday')->setTime(14, 0);   // medica qua 14-18
        $terca = now()->next('Tuesday')->setTime(9, 0);       // pediatra / enfermeiro

        // Futuras: confirmadas
        Consulta::factory()->confirmada()->comAutores($rececionista)
            ->for($principal)->for($medica)->create(['inicio' => $segunda->copy()->setTime(9, 30)]);
        Consulta::factory()->confirmada()->comAutores($rececionista)
            ->for($todos[1])->for($medica)->create(['inicio' => $quarta->copy()->setTime(14, 30)]);
        Consulta::factory()->confirmada()->comAutores($rececionista)
            ->for($todos[2])->for($pediatra)->create(['inicio' => $terca->copy()->setTime(10, 0)]);

        // Futuras: pedidas (aparecem na lista de pedidos da receção)
        Consulta::factory()->pedida()->for($todos[3])->for($medica)->create(['inicio' => $segunda->copy()->setTime(10, 0)]);
        Consulta::factory()->pedida()->for($todos[4])->for($enfermeiro)->create(['inicio' => $terca->copy()->setTime(11, 0)]);
        Consulta::factory()->pedida()->for($todos[5])->for($dermato)->create(['inicio' => now()->next('Monday')->setTime(15, 0)]);

        // Em espera: na mesma vaga de uma consulta confirmada/pedida
        Consulta::factory()->emEspera()->for($todos[6])->for($medica)->create(['inicio' => $segunda->copy()->setTime(9, 30)]);

        // Passadas
        Consulta::factory()->realizada()->comAutores($rececionista)->for($principal)->for($medica)->create();
        Consulta::factory()->realizada()->comAutores($rececionista)->for($todos[7])->for($pediatra)->create();
        Consulta::factory()->faltou()->comAutores($rececionista)->for($todos[8])->for($medica)->create();

        // Rejeitadas e canceladas (não ocupam vaga)
        Consulta::factory()->rejeitada()->comAutores($rececionista)->for($todos[9])->for($medica)->create();
        Consulta::factory()->cancelada()->comAutores($rececionista)->for($todos[10])->for($enfermeiro)->create();
    }
}