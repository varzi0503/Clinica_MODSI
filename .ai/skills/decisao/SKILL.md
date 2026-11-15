---
name: decisao
description: Registar uma decisão técnica nova em docs/decisoes.md no formato do projeto (Dnn), incluindo substituições de decisões antigas. Usar quando o Diogo fecha uma decisão durante uma issue.
---

# Registar uma decisão

1. A próxima entrada é a seguinte à última Dnn de `docs/decisoes.md`.
2. Acrescentar a linha à tabela do topo (`| Dnn | Decisão | Área |`). Áreas: Stack, Interface, Âmbito, Modelo de dados, Segurança, Processo.
3. Secção nova, no formato das existentes:

       ## Dnn — Título curto

       **Decisão:** o que fica decidido, concreto (tabelas, colunas, estados, quem faz o quê).

       **Porquê:** razões, ligadas a RNF/RF ou a decisões anteriores (Dxx).

       **Alternativa rejeitada:** o que se pôs de parte e porquê.

       **Consequência:** o que muda no código, nos testes ou no relatório (opcional).

4. Para substituir uma decisão, **não se apaga**: no topo da antiga escreve-se `> **Substituída por Dnn** (data). Mantida como registo histórico.`, e o título na tabela ganha "(substituída por Dnn)".
5. Só decisões que o Diogo tomou. As recomendações do agente não são decisões até ele as aceitar.
6. Português europeu, frases curtas, sem jargão desnecessário: é texto que os três têm de saber defender.
