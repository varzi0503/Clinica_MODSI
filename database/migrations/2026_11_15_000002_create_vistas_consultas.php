<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #36: vistas SQL para a agenda e para as estatísticas (issue #73).
 *
 * SQL portátil, para correr em MySQL e no SQLite dos testes: CASE em vez de IF(),
 * sem funções próprias de um motor. Cada vista é apagada antes de ser criada porque
 * o SQLite não tem CREATE OR REPLACE VIEW e o migrate:fresh não apaga vistas por omissão.
 *
 * Se uma migration posterior alterar com change() uma tabela usada aqui, tem de apagar
 * estas vistas antes e voltar a criá-las depois: o SQLite reconstrói a tabela e falha.
 */
return new class extends Migration
{
    /** Contagens por estado, comuns às estatísticas por profissional e por especialidade. */
    private const CONTAGENS = <<<'SQL'
        count(c.id) as total,
        sum(case when c.estado = 'em_espera' then 1 else 0 end) as em_espera,
        sum(case when c.estado = 'pedida' then 1 else 0 end) as pedidas,
        sum(case when c.estado = 'confirmada' then 1 else 0 end) as confirmadas,
        sum(case when c.estado = 'rejeitada' then 1 else 0 end) as rejeitadas,
        sum(case when c.estado = 'cancelada' then 1 else 0 end) as canceladas,
        sum(case when c.estado = 'realizada' then 1 else 0 end) as realizadas,
        sum(case when c.estado = 'faltou' then 1 else 0 end) as faltas
        SQL;

    public function up(): void
    {
        foreach ($this->vistas() as $nome => $select) {
            DB::statement("drop view if exists {$nome}");
            DB::statement("create view {$nome} as {$select}");
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->vistas()) as $nome) {
            DB::statement("drop view if exists {$nome}");
        }
    }

    /** @return array<string, string> nome da vista => SELECT */
    private function vistas(): array
    {
        $contagens = self::CONTAGENS;

        return [
            // Uma linha por consulta, já com os nomes: serve a agenda de um profissional
            // (where profissional_id = ?) e as listagens que hoje juntam quatro tabelas.
            'v_consultas_detalhe' => <<<'SQL'
                select
                    c.id as consulta_id,
                    c.inicio,
                    c.estado,
                    c.vaga_ocupada,
                    c.data_pedido,
                    c.data_estado,
                    c.motivo_consulta,
                    c.reagendada_de_id,
                    c.paciente_id,
                    p.nome as paciente_nome,
                    p.num_utente as paciente_num_utente,
                    c.profissional_id,
                    u.name as profissional_nome,
                    pr.tipo as profissional_tipo,
                    c.especialidade_id,
                    e.nome as especialidade_nome
                from consultas c
                join pacientes p on p.id = c.paciente_id
                join profissionais pr on pr.id = c.profissional_id
                join users u on u.id = pr.user_id
                join especialidades e on e.id = c.especialidade_id
                SQL,

            // Só aparecem os estados que têm consultas; quem lê preenche os outros com zero.
            'v_estatisticas_estado' => <<<'SQL'
                select c.estado, count(*) as total
                from consultas c
                group by c.estado
                SQL,

            // LEFT JOIN: um profissional sem consultas aparece com tudo a zero.
            'v_estatisticas_profissional' => <<<SQL
                select
                    pr.id as profissional_id,
                    u.name as profissional_nome,
                    pr.tipo as profissional_tipo,
                    {$contagens}
                from profissionais pr
                join users u on u.id = pr.user_id
                left join consultas c on c.profissional_id = pr.id
                group by pr.id, u.name, pr.tipo
                SQL,

            'v_estatisticas_especialidade' => <<<SQL
                select
                    e.id as especialidade_id,
                    e.nome as especialidade_nome,
                    {$contagens}
                from especialidades e
                left join consultas c on c.especialidade_id = e.id
                group by e.id, e.nome
                SQL,
        ];
    }
};
