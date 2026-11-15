---
paths:
  - 'database/migrations/**'
---

# Migrations

## Nunca editar migrations integradas
Uma migration que já está no `main` não se edita nem se compacta: cria-se uma nova. Testar com `php artisan migrate:fresh --seed` em MySQL e com a suite em SQLite.

## CHECKs só em MySQL
Restrições CHECK só dentro de `if (DB::getDriverName() === 'mysql')`. O SQLite não as aceita depois de criada a tabela.

## vaga_ocupada é gerada e nunca se escreve
`consultas.vaga_ocupada` é uma coluna gerada STORED (1 nos estados que ocupam vaga, NULL nos restantes). Nunca aparece em inserts, updates, factories ou seeders. A fórmula tem de coincidir com `EstadoConsulta::ocupaVaga()`.

## Mudar o enum estado
Seguir `2026_11_11_000001_add_estado_faltou_a_consultas.php`: no MySQL saem e voltam a coluna gerada e os UNIQUE; no SQLite é tudo `change()` num só blueprint.

## Vistas e ->change() no SQLite
Vistas com `DB::statement('CREATE VIEW …')` em SQL portátil (`CASE`, nunca `IF()`), e `down()` com `DROP VIEW IF EXISTS`. No SQLite, um `->change()` numa tabela usada por uma vista reconstrói a tabela e pode falhar: a migration apaga as vistas, faz a alteração e volta a criá-las.

## Chave estrangeira nova numa tabela existente: ADD COLUMN direto no SQLite
No SQLite, acrescentar uma chave estrangeira pelo Blueprint (`foreignId()->constrained()`) reconstrói a tabela, e a reconstrução relê os enums como varchar e perde o CHECK. Em `consultas` isso apaga a validação do estado nos testes sem nenhum teste falhar. Dentro de `if (DB::getDriverName() === 'sqlite')` usar `DB::statement('alter table … add column … references …')`; em MySQL, o Blueprint normal. Ver `2026_11_15_000001_add_reagendada_de_a_consultas.php`.

## Vistas: DROP VIEW IF EXISTS antes de CREATE VIEW
O `migrate:fresh` não apaga vistas por omissão, por isso à segunda vez um `CREATE VIEW` simples falha com "already exists". O SQLite não tem `CREATE OR REPLACE VIEW`. No `up()`, apagar cada vista com `DROP VIEW IF EXISTS` antes de a criar. Ver `2026_11_15_000002_create_vistas_consultas.php`.
