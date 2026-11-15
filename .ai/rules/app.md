---
paths:
  - 'app/**'
---

# App

## Carbon 3: diffInDays devolve float
`diffInDays()` devolve float (6.999… numa semana). Converter sempre com `(int)`.

## createFromFormat com | para zerar segundos
Interpretar datas de formulário com `CarbonImmutable::createFromFormat('Y-m-d\TH:i|', …)`. O `|` põe os segundos a zero; sem ele, o PHP preenche-os com a hora atual e a vaga deixa de coincidir.

## Estados da consulta só por registarPedido() e mudarEstado()
Nenhum código de aplicação escreve `consultas.estado` nem `historico_estados` diretamente: só `Consulta::registarPedido()` e `->mudarEstado()`, que fazem a transação com o histórico e validam a matriz (D19, D23).
