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
