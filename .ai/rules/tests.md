---
paths:
  - 'tests/**'
---

# Tests

## Classes PHPUnit, não sintaxe Pest
Os testes são classes PHPUnit no estilo dos existentes em `tests/Feature/`. Não converter para `it()`/`test()` do Pest, mesmo com a skill `pest-testing` instalada.

## travelTo() para fixar a data
Testes que dependem de datas (vagas, agenda, registar realizada/falta) fixam o relógio com `$this->travelTo(...)`. Nunca depender do dia em que a suite corre.
