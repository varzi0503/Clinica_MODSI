<laravel-boost-guidelines>
=== .ai/clinica rules ===

# Clínica MODSI

Plataforma de gestão de consultas médicas (MODSI, ISEP). Laravel 13, PHP 8.4 (no servidor AVE, 8.3: D40), MySQL 8.0, Blade + Bootstrap 5, AJAX com `fetch`, testes PHPUnit sobre SQLite em memória. O código congela a **6 dez. 2026**.

## Ler primeiro

- `docs/estado-projeto.md` (estado e lições), `docs/decisoes.md` (D01+, **fechadas**: para mudar uma, acrescenta-se uma entrada nova que a substitui; nunca se apaga) e `CONTRIBUTING.md`.
- `.ai/rules/index.md` antes de editar qualquer ficheiro: as regras estão agrupadas por pasta.
- Confirmar `php -v` = 8.4 (ou 8.3). Se não for, parar e avisar o Diogo. O `composer.json` fixa a plataforma em PHP 8.3.27 para as dependências servirem no AVE (D40): não a retirar.

## Domínio

- **Atores:** paciente, profissional (médico ou enfermeiro, em `profissionais.tipo`), rececionista, administrador. O admin só gere contas e perfis; o resto é da receção (D26).
- **Conta ≠ ficha:** `users` separado de `pacientes` / `profissionais`. Um paciente pode existir sem conta (ficha de balcão); só a receção associa uma conta a uma ficha (D11).
- **Estados** (`App\Enums\EstadoConsulta`): `em_espera`, `pedida`, `confirmada`, `rejeitada`, `cancelada`, `realizada`, `faltou`.
- **Transições:** em_espera → pedida | cancelada; pedida → confirmada | rejeitada | cancelada; confirmada → realizada | faltou | cancelada. Os outros são terminais. Uma consulta só nasce `pedida` ou `em_espera`.
- **Ocupam a vaga:** `pedida`, `confirmada`, `realizada`, `faltou`. É a coluna gerada `consultas.vaga_ocupada`, com `UNIQUE` por profissional e por paciente (D20).
- Consultas de 30 min; horário semanal repetido; a especialidade tem de ser uma das do profissional (FK composta).
- A receção marca: nasce `pedida` e passa a `confirmada` na mesma transação (D24). O paciente pede: fica `pedida`. Rejeitar exige motivo.

## Peças a reutilizar (não reescrever)

- `Consulta::registarPedido()` e `->mudarEstado()`: única forma de criar ou mudar estados. Lançam `TransicaoEstadoInvalida`.
- `App\Services\Disponibilidade`: `vagasLivres()` e `estaLivre()`. Não recalcular vagas à mão.
- `App\Services\Marcacoes`: pedir, marcar, rejeitar, cancelar, reagendar e lista de espera. Tudo o que liberta uma vaga promove a lista de espera **na mesma transação** (D32).
- Middleware `perfil:<nome>` (`EnsurePerfil`), com as constantes de `Perfil`. Posse dos dados por policy. Sempre no servidor.
- `clinica.pedirJson` (`resources/js/ajax.js`). Respostas D28: 2xx `{message}`, 422 validação, 409 conflito de estado, 403 sem acesso. O formulário funciona também sem JS.
- Rotas por perfil em `routes/web.php`: `admin.*`, `rececao.*`, `profissional.*`, `paciente.*`.
- Textos em português com `__()`; tradução em `lang/en.json`.

## Regras de trabalho

- **Nunca** `git push` para o `main`, `gh pr create` nem `gh pr merge`: é o Diogo que abre e integra os PRs. Numa sessão local não há push nenhum; numa sessão na cloud, só para o branch `claude/*` da sessão e só se o Diogo o autorizar.
- Um branch por issue: `<tipo>/<nº-real-da-issue>-<descrição>` (`feat`, `fix`, `db`, `docs`, `chore`, `refactor`). Nunca números de exemplo nem placeholders. Confirmar antes que o branch não existe.
- Commits `<tipo>: <o que muda, no presente>`, em português, autor `varzi0503 <1212100@isep.ipp.pt>`.
- Antes de cada commit: `vendor/bin/pint --dirty`, `npm run build` (os testes de páginas precisam do manifesto do Vite) e `php artisan test`. Dizer o número de testes antes e depois.
- Migrations novas: `php artisan migrate:fresh --seed` em MySQL **e** a suite em SQLite.
- Decisão em falta em `docs/decisoes.md`: parar e perguntar, com recomendação e alternativa. Depois de decidida, entrada Dnn no mesmo PR (skill `decisao`).
- No fim de cada tarefa: dizer o que foi e o que não foi verificado, e dar o bloco PowerShell de entrega (skill `entrega`).
- Na resposta final, tudo o que o Diogo tem de decidir ou opinar fica numa única secção, no fim.
- Mensagens de WhatsApp para o Timóteo ou o Filipe só quando for preciso desenhar um esquema, fazer um diagrama ou alterar o relatório: nesse caso, escrever a mensagem pronta a enviar, com o que fazer e como. Não escrever mensagens só para pedir testes.
- Skills do projeto (em `.ai/skills/`): `caso-de-uso`, `migracoes`, `entrega`, `decisao`.
- A skill `infer-conventions` do Boost nunca corre em modo automático: mostrar cada convenção ao Diogo antes de a gravar em `.ai/rules/`.

=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

</laravel-boost-guidelines>
