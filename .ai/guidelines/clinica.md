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
