---
name: caso-de-uso
description: Implementar um caso de uso (issue) da Clínica MODSI de ponta a ponta — rota, Form Request, policy, serviço, vista, AJAX, traduções e testes. Usar sempre que se começa uma issue de funcionalidade.
---

# Implementar um caso de uso

## Antes de escrever código
1. `gh issue view <nº>`: lê o corpo e os comentários. Cita a issue pelo número (as numerações UC das issues e do relatório não coincidem).
2. Procura em `docs/decisoes.md` as decisões que se aplicam. Se faltar uma, para e pergunta, com recomendação e alternativa.
3. Confirma que o branch `<tipo>/<nº>-<descrição>` não existe e cria-o a partir do `main` atualizado.

## Onde fica cada coisa
- Rotas: no grupo do perfil em `routes/web.php` (`admin.*`, `rececao.*`, `profissional.*`, `paciente.*`). Nunca fora de `auth` + `perfil:`.
- Controlador fino: `app/Http/Controllers/<Perfil>/`. Valida com um Form Request (`app/Http/Requests/<Perfil>/`), autoriza com policy e delega.
- Regras de negócio da consulta: `App\Services\Marcacoes`. Estados só por `registarPedido()` e `mudarEstado()`. Tudo o que liberta uma vaga chama a promoção da lista de espera **na mesma transação**.
- Vagas: `App\Services\Disponibilidade`. Não recalcular à mão.
- Vista: `resources/views/<perfil>/`, estendendo `layouts.app`, com Bootstrap 5 e responsiva.
- AJAX: `clinica.pedirJson` (`resources/js/ajax.js`). Respostas D28: 2xx `{message}`, 422 validação, 409 conflito de estado, 403 sem acesso. O formulário tem de funcionar também sem JS.
- Textos: `__('Texto em PT')`, mais a entrada em `lang/en.json` por ordem alfabética. Sem `trans_choice`.

## Testes mínimos (classe PHPUnit em `tests/Feature/`, com factories e `travelTo`)
- Caminho feliz, incluindo o histórico de estados quando há transição.
- Perfil errado → 403. Dono errado (outro paciente/profissional) → 403.
- Dados inválidos → 422 (JSON) ou erros de sessão.
- Conflito: transição inválida → 409; vaga ocupada ou corrida → mensagem, nunca 500.
- Para cada texto novo, confirmar que a chave existe no `en.json`.

## Fechar
- `vendor/bin/pint --dirty`, `npm run build` (se houver JS), `php artisan test`. Indicar o número de testes antes e depois.
- Se houve decisão nova: entrada Dnn em `docs/decisoes.md` (usar a skill `decisao`).
- Corpo do PR para o Diogo: `Closes #<nº>`, "Como testar" com passos no browser **e** um caso que deve falhar, e as contas a usar.
- Entregar com a skill `entrega`. Nunca fazer push.
