# Como trabalhamos

## Papéis

| Elemento | Papel |
|---|---|
| Diogo (A) | Implementação: código da aplicação, migrations, decisões técnicas; faz o merge dos PRs de código |
| Timóteo (B) | Testes no `main` depois de cada merge, dados de demonstração (seeders), plano de testes, manual de utilizador |
| Filipe (C) | Testes no `main` depois de cada merge, diagramas (UC, ER, classes), descrição dos casos de uso, guião da demo |

Todas as tarefas, de código ou não, são issues no quadro do Project, atribuídas a quem as faz.

## Fluxo de uma tarefa de código (A)

1. No quadro, move a issue de **Pronto** para **Em curso**.
2. Cria um branch a partir do `main` atualizado:
   ```powershell
   git switch main
   git pull
   git switch -c feat/53-pedir-consulta
   ```
3. Faz commits pequenos à medida que avanças.
4. Antes de abrir o PR, com o `main` integrado no branch:
   ```powershell
   npm run build
   php artisan test
   git push -u origin HEAD
   ```
5. Abre o PR com `Closes #53` na descrição e, em **Como testar**, os passos no browser e o resultado esperado.
6. Faz o merge (**Squash and merge**, sem aprovação obrigatória) e apaga o branch. A issue fecha sozinha.
7. Avisa B e C de que há um PR novo para testar.

## Fluxo de teste (B e C), depois do merge

Os PRs são testados **no `main`**, depois de integrados. Cada PR de funcionalidade é testado por um de vocês, alternadamente.

1. Atualiza o `main` e prepara a base de dados:
   ```powershell
   git switch main
   git pull
   composer install          # na rede do ISEP, usar o hotspot
   npm install
   npm run build
   php artisan migrate:fresh --seed
   php artisan test
   php artisan serve
   ```
2. Segue os passos de **Como testar** do PR no browser e experimenta também um caso que deva falhar (dados inválidos, perfil sem acesso, vaga já ocupada).
3. Deixa um comentário **no PR já integrado** com o que testaste e o resultado:
   ```
   Testado no main (a1b2c3d): pedido de consulta como paciente (ok), vaga já ocupada (erro mostrado, ok),
   acesso como rececionista (bloqueado, ok). Encontrado: data no passado é aceite → aberta a issue #110.
   ```
4. Se encontrares um erro, abre uma issue `fix` com os passos para o reproduzir e liga-a ao PR. Quem a corrige é o A.

## Fluxo de uma tarefa não-código (B e C)

Seeders e ficheiros do repositório (`docs/`, diagramas) seguem o fluxo de código: branch, PR com `Closes #N`. O A integra-os depois de os testar. Capítulos do relatório em LaTeX ficam no Prism, e a issue fecha-se à mão com um comentário a indicar o capítulo ou a secção.

## Nomes de branches

`<tipo>/<número-da-issue>-<descrição-curta>`

| Tipo | Quando |
|---|---|
| `feat` | Funcionalidade nova (um caso de uso, uma página) |
| `fix` | Correção de um erro |
| `db` | Migrations, vistas, seeders |
| `docs` | Relatório, README, diagramas |
| `chore` | Configuração, dependências |

Exemplos: `feat/53-pedir-consulta`, `db/37-seeders-demo`, `docs/31-diagrama-uc`.

## Mensagens de commit

`<tipo>: <o que muda, no presente>`

```
feat: formulário de pedido de consulta
fix: data no passado aceite no pedido de consulta
db: seeders com profissionais e horários de exemplo
```

## Regras

- **Nunca** fazer push direto para o `main`, nem `--force` no `main`. Tudo entra por PR (o GitHub recusa o resto).
- **Nunca** fazer commit do `.env` nem de passwords.
- Uma issue por PR. Se o PR passa de ~400 linhas alteradas, divide-o.
- Testar um PR é correr a aplicação no browser, não ler o código.
- Migrations já integradas no `main` **não se editam**: cria uma migration nova.
- Textos visíveis escrevem-se em português com `__('…')`; a tradução inglesa vai para `lang/en.json`, por ordem alfabética. Nunca texto solto nas vistas.
- Ao resolver um conflito no `lang/en.json`, valida o ficheiro antes de concluir:
  ```powershell
  php -r "json_decode(file_get_contents('lang/en.json'), flags: JSON_THROW_ON_ERROR); echo 'ok';"
  ```
- Regras de acesso (perfil, posse dos dados) verificam-se no servidor, nunca só escondendo botões.
- O código congela a **6 de dezembro**. Depois disso só entram PRs `fix`.
