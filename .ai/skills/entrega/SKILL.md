---
name: entrega
description: Fechar uma tarefa da Clínica MODSI e entregá-la ao Diogo — verificações finais e bloco PowerShell para push e PR. Usar no fim de cada issue ou refatoração.
---

# Entregar uma tarefa

O agente **nunca** faz `git push`, `gh pr create` nem `gh pr merge`.

## Verificações
- `git status` limpo, exceto ficheiros que o Diogo tenha de rever.
- `lang/en.json` é JSON válido: `php -r "json_decode(file_get_contents('lang/en.json'), flags: JSON_THROW_ON_ERROR); echo 'ok';"`
- `php artisan test` verde, com o número de testes indicado.
- O branch tem por base o `origin/main` atual (`git fetch` + `git merge-base --is-ancestor origin/main HEAD`).
- Dizer o que foi e o que não foi verificado (browser, MySQL, telemóvel).

## Bloco para o Diogo (PowerShell)
Usar números e caminhos reais, nunca placeholders. Se depender de um PR por integrar, incluir `Test-Path` com um ficheiro desse PR.

    & {
        git switch feat/70-criar-profissional
        if ($LASTEXITCODE) { throw "Branch não encontrado. Parei." }
        php artisan test
        if ($LASTEXITCODE) { throw "Testes a falhar. Parei." }
        git push -u origin HEAD
        gh pr create --base main --title "feat: UC22 — receção cria profissional" --body-file pr-body.md
    }

O merge vai sempre num bloco separado:

    gh pr merge --squash --delete-branch

## Depois do merge
Lembrar o Diogo de avisar o Timóteo e o Filipe de que há um PR para testar no `main` (D29).
