---
name: migracoes
description: Escrever migrations, vistas SQL e alterações de esquema que funcionam em MySQL 8.0 (desenvolvimento e demo) e em SQLite em memória (testes). Usar sempre que se toca em database/migrations.
---

# Migrations da Clínica MODSI

## Regras
- Nunca editar uma migration que já está no `main`: criar uma nova.
- Nomes Laravel em inglês/convenção (`paciente_id`, plural); o ER do relatório usa português (D18).
- Restrições CHECK só dentro de `if (DB::getDriverName() === 'mysql')`. O SQLite não as aceita depois de criada a tabela.
- `consultas.vaga_ocupada` é `STORED` e gerada: nunca se escreve. A fórmula tem de coincidir com `EstadoConsulta::ocupaVaga()` (há um teste).
- Mudar o enum `estado`: seguir a migration `2026_11_11_000001_add_estado_faltou_a_consultas.php`. No MySQL saem e voltam a coluna gerada e os UNIQUE; no SQLite é tudo `change()` num só blueprint.
- Colunas novas com default: o mesmo default em `$attributes` no modelo.
- Vistas: `DB::statement('CREATE VIEW …')` com SQL portátil (`CASE`, nunca `IF()`), e `down()` com `DROP VIEW IF EXISTS`. Uma alteração posterior a uma tabela usada por uma vista pode falhar no SQLite, que reconstrói a tabela. Nesse caso a migration apaga as vistas e volta a criá-las à volta da alteração.

## Verificar sempre nos dois motores
1. `php artisan migrate:fresh --seed` em MySQL.
2. `php artisan migrate:rollback` da migration nova e `migrate` outra vez (o `down()` funciona).
3. `php artisan test`, que corre em SQLite.
4. Se a migration tem restrições, um teste em `EsquemaConsultasTest` (ou equivalente) que prove que a BD recusa o caso inválido.
