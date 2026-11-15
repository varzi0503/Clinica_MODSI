---
paths:
  - 'app/Models/**'
---

# Models

## Defaults da BD também em $attributes
Uma coluna com default na BD precisa do mesmo default em `$attributes` no modelo. Sem isso, um modelo acabado de criar (ou o `actingAs()` nos testes) vê `null` — aconteceu com `users.ativo`, e o middleware tratava a conta como desativada.

## #[Table] para nomes em português
Modelos com nome português levam `#[Table]` explícito: o Laravel pluralizaria `Perfil` para `perfils` e `Profissional` para `profissionals`.
