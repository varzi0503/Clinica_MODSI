# Como trabalhamos

## Papéis

| Elemento | Papel |
|---|---|
| Diogo (A) | Implementação: código da aplicação, migrations, decisões técnicas |
| Timóteo (B) | Testes de cada PR, dados de demonstração (seeders), plano de testes, manual de utilizador |
| Filipe (C) | Testes de cada PR, diagramas (UC, ER, classes), descrição dos casos de uso, guião da demo |

Todas as tarefas, de código ou não, são issues no quadro do Project, atribuídas a quem as faz.

## Fluxo de uma tarefa de código (A)

1. No quadro, move a issue de **Pronto** para **Em curso**.
2. Cria um branch a partir do `main` atualizado:
   ```powershell
   git switch main
   git pull
   git switch -c feat/52-uc07-pedir-consulta
   ```
3. Faz commits pequenos à medida que avanças.
4. Antes de abrir o PR:
   ```powershell
   git switch main; git pull; git switch -
   git merge main
   php artisan test
   git push -u origin HEAD
   ```
5. Abre o PR com `Closes #52` na descrição e, em **Como testar**, os passos e o resultado esperado.
6. Move a issue para **Em revisão** e marca B ou C como responsável pelo teste.
7. O merge (**Squash and merge**) não exige aprovação. Faz-se depois de o teste estar feito ou, se for urgente, antes, com um comentário a explicar porquê. Apaga o branch no fim.

## Fluxo de teste de um PR (B ou C)

1. Traz o branch do PR e prepara a base de dados:
   ```powershell
   git fetch
   git switch feat/52-uc07-pedir-consulta
   composer install          # na rede do ISEP, usar o hotspot
   php artisan migrate:fresh --seed
   php artisan test
   ```
2. Segue os passos de **Como testar** do PR no browser e experimenta também um caso que deva falhar (dados inválidos, perfil sem acesso).
3. Deixa um comentário no PR com o que testaste e o resultado:
   ```
   Testado: pedido de consulta como paciente (ok), vaga já ocupada (erro mostrado, ok),
   acesso como rececionista (bloqueado, ok). Encontrado: data no passado é aceite.
   ```
4. Se encontrares um erro, abre uma issue `fix` ligada ao PR em vez de o corrigir tu.

## Fluxo de uma tarefa não-código (B ou C)

Seeders e ficheiros do repositório (`docs/`, diagramas) seguem o fluxo de código: branch, PR, `Closes #N`. Capítulos do relatório em LaTeX ficam no Prism, e a issue fecha-se à mão com um comentário a indicar o capítulo ou a secção.

## Nomes de branches

`<tipo>/<número-da-issue>-<descrição-curta>`

| Tipo | Quando |
|---|---|
| `feat` | Funcionalidade nova (um caso de uso, uma página) |
| `fix` | Correção de um erro |
| `db` | Migrations, vistas, seeders |
| `docs` | Relatório, README, diagramas |
| `chore` | Configuração, dependências |

Exemplos: `feat/52-uc07-pedir-consulta`, `db/34-restricao-horario`, `docs/31-diagrama-uc`.

## Mensagens de commit

`<tipo>: <o que muda, no presente>`

```
feat: formulário de pedido de consulta
fix: horário cancelado volta a aparecer como livre
db: seeders com profissionais e horários de exemplo
```

## Regras

- **Nunca** fazer push direto para o `main`, nem `--force` no `main`. Tudo entra por PR.
- **Nunca** fazer commit do `.env` nem de passwords.
- Uma issue por PR. Se o PR passa de ~400 linhas alteradas, divide-o.
- Testar um PR é correr o branch localmente, não ler o código.
- Migrations já integradas no `main` **não se editam**: cria uma migration nova.
- Textos visíveis escrevem-se em português com `__('…')`; a tradução inglesa vai para `lang/en.json`. Nunca texto solto nas vistas.
- Regras de acesso (perfil, posse dos dados) verificam-se no servidor, nunca só escondendo botões.
- O código congela a **6 de dezembro**. Depois disso só entram PRs `fix`.
