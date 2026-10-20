# Como trabalhamos

## Fluxo de uma tarefa

1. No quadro do Project, pega numa issue tua da coluna **Pronto** e move-a para **Em curso**.
2. Atualiza o `main` e cria um branch a partir dele:
   ```powershell
   git switch main
   git pull
   git switch -c feat/44-readme
   ```
3. Faz commits pequenos à medida que avanças.
4. Antes de abrir o PR:
   ```powershell
   git switch main; git pull; git switch -   # volta ao teu branch
   git merge main                            # traz o que os outros já integraram
   php artisan test
   git push -u origin HEAD
   ```
5. Abre o pull request no GitHub. Escreve `Closes #44` na descrição para a issue fechar sozinha no merge.
6. Pede revisão a outro elemento e move a issue para **Em revisão**.
7. Depois de aprovado, quem abriu o PR faz o merge (**Squash and merge**) e apaga o branch.

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
db: vista de agenda por médico
```

## Regras

- **Nunca** fazer push direto para o `main`, nem `--force` no `main`.
- **Nunca** fazer commit do `.env` nem de passwords.
- Uma issue por PR. Se o PR passa de ~400 linhas alteradas, divide-o.
- Quem revê corre o branch localmente, não aprova só a ler o código.
- Migrations já integradas no `main` **não se editam**: cria uma migration nova.
- Textos visíveis ao utilizador vão para os ficheiros `lang/pt` e `lang/en`, nunca escritos diretamente nas vistas.
- Regras de acesso (perfil, posse dos dados) verificam-se no servidor, nunca só escondendo botões.
