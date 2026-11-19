# Clínica MODSI — estado do projeto e contexto para novas sessões

> Atualizado a 11 nov. 2026, depois do merge do PR #108 e do patch `docs-decisoes`. Ler isto antes de qualquer trabalho.
> Fonte de verdade do código: `main` de `varzi0503/Clinica_MODSI` (público).
> As justificações formais das decisões ficam em `docs/decisoes.md` no repositório (D01–D30, depois do patch `docs-decisoes`).

## 1. Contexto

- **UC:** MODSI (ISEP). **Tema 1:** plataforma web de gestão de consultas médicas, com todas as extensões opcionais (notificações, lista de espera, observações/documentos).
- **Grupo:** Diogo (A), Timóteo (B, GitHub `timioteo`), Filipe (C, GitHub `filipeduarte05`). O Diogo é `varzi0503` e tem todas as decisões técnicas desde 3 nov.
- **Prazos:** código congelado a **6 dez. 2026** (depois só PRs `fix`); entrega a **14 dez. 2026**; a última semana é para relatório e demo (10 min).
- **Relatório:** LaTeX no Prism (`Cap_N/ChapN.tex`).
- **Professor:** pediu enfermagem, o que levou a "profissional de saúde" = médico ou enfermeiro. Admin só gere utilizadores e perfis; o resto é da receção.

## 2. Stack e ambiente

- Laravel 13, PHP 8.4, MySQL 8.0, Blade + Bootstrap 5, AJAX com `fetch` (sem SPA), Vite. Testes com Pest/PHPUnit sobre SQLite em memória.
- Os três usam Windows, PowerShell e Laravel Herd. **A rede do ISEP bloqueia o Packagist:** para usar o `composer`, ligar ao hotspot do telemóvel.
- Depois de qualquer patch com JS: `npm run build`. Depois de patches com migrations ou seeder: `php artisan migrate:fresh --seed`.
- Seeder: perfis fixos, `admin@example.com` (administrador) e `test@example.com` (rececionista), ambos com a palavra-passe `password`. **Ainda não há dados de demonstração** (ver #37).
- Aviso inofensivo do VS Code: `APP_PREVIOUS_KEYS` não está no `.env`. É opcional; `APP_PREVIOUS_KEYS=` no `.env` cala o aviso.

## 3. Forma de trabalhar (importante)

### Divisão de trabalho
- **O Diogo implementa todo o código** (decisão de 9 nov.), para avançar depressa e sem dependências entre elementos.
- **O Timóteo e o Filipe testam no `main` depois de cada merge** e deixam um comentário no PR já integrado. Também fazem as tarefas não-código: seeders, plano de testes, manual, diagramas, casos de uso, guião da demo.
- Está escrito no `CONTRIBUTING.md` da raiz, desde o PR #103.
- **Objetivo declarado:** os colegas não podem ficar prejudicados aos olhos do professor, que tem acesso ao repositório. As issues de código estão atribuídas ao Diogo; a divisão deve ser declarada, não escondida.
- O `main` tem uma regra: tudo entra por PR, sem aprovação obrigatória. Push direto para o `main` é recusado.

### Como o Claude entrega o trabalho
- **O Claude não tem acesso de escrita ao repositório e não faz push**, por decisão do Diogo. Clona o repositório público e entrega **patches** (`git format-patch -1 --stdout > nome.patch`).
- Os patches já levam o autor `varzi0503 <1212100@isep.ipp.pt>`: configurar `git config user.name/user.email` no clone antes de fazer commit. O Diogo aplica-os com `git am -3`.
- **Avisos do hook "unpushed commits":** responder que não se faz push, porque o branch local é só a cópia de trabalho.
- Antes de entregar, confirmar que o patch aplica sobre o `origin/main` atual e que o `lang/en.json` continua JSON válido.

### Modelo de comandos para o Diogo (PowerShell)

O `throw` dentro de `& { }` é o que impede de continuar depois de um erro. Um comando git que falha não para o bloco sozinho, por isso cada passo crítico leva uma verificação a seguir.

```powershell
& {
    git switch main; git pull
    # se depender de um PR ainda por integrar:
    if (-not (Test-Path caminho/de/um/ficheiro-do-PR-anterior.php)) { throw "O #NN ainda não está no main. Parei." }
    git switch -c feat/NN-descricao
    if ($LASTEXITCODE) { throw "Não consegui criar o branch. Parei." }
    git am -3 "D:\Downloads\nome.patch"
    npm run build
    php artisan test               # N testes
}
```

Abrir o PR é um passo à parte: `git push -u origin HEAD` e depois `gh pr create --base main --title "..." --body "Closes #NN"`. **O `gh pr merge` vai sempre num bloco separado**, para não ser colado junto com o resto.

### Lições que custaram tempo (não repetir)
1. **Placeholders nos comandos são copiados à letra.** Nunca escrever `<nº-da-issue>` nem números de exemplo: usar o número real da issue.
2. **Branch já existente:** o `git switch -c` falha e o `git am` aplica-se no branch errado (aconteceu duas vezes, uma delas no `main`). É para isso que serve o `if ($LASTEXITCODE)`.
3. **Dependências entre patches:** dizer em que PR se apoia e incluir a verificação com `Test-Path`.
4. **`lang/en.json`:** chaves por ordem alfabética. Dois patches que acrescentem chaves vizinhas entram em conflito; nesse caso empilhar um sobre o outro. Depois de resolver um conflito à mão, validar o JSON (há um comando no CONTRIBUTING).
5. **Não usar `trans_choice()`.** Sem a chave no `pt.json`, cai no idioma de fallback (`en`) e mostra inglês a quem tem a interface em português. O `__()` não tem esse problema. Para plurais, usar duas mensagens normais.
6. **Chaves de tradução reutilizadas:** `"Início"` = "Home" (navbar). Para "hora de início" usar `"Hora de início"`.
7. **Carbon 3:** `diffInDays()` devolve float (6.999… numa semana). Converter com `(int)`.
8. **`CarbonImmutable::createFromFormat('Y-m-d\TH:i|', …)`:** o `|` põe os segundos a zero. Sem ele, o PHP preenche-os com a hora atual.
9. Novos atributos com default na BD precisam de default também no modelo (`$attributes`). Aconteceu com `ativo` no `User`: o `actingAs()` via `null` e o middleware tratava a conta como desativada.

### Ambiente do Claude (limitações)
- O ambiente do Claude só tem PHP 8.3, sem forma de instalar o 8.4 (executar um binário descarregado foi bloqueado, e bem). Por isso **o `php artisan test` não corre aqui:** é o Diogo que o corre em cada patch.
- O Claude valida a lógica com um harness próprio, que corre o Eloquent e as migrations sobre **MySQL 8.0 local e SQLite**. O JavaScript é testado com **Playwright/Chromium**, com páginas estáticas que reproduzem o HTML do Blade e respostas simuladas.
- Dizer sempre claramente o que foi e o que não foi verificado.

## 4. Decisões de domínio e de modelo (fechadas)

**Âmbito e atores**
- Atores: paciente, profissional (médico ou enfermeiro), rececionista e administrador.
- **Admin:** cria contas e atribui um dos perfis existentes. Na prática só cria e muda entre `administrador` e `rececionista`.
- **Receção:** confirma e rejeita pedidos, marca consultas, cria profissionais (conta + ficha), gere especialidades e vê estatísticas.

**Perfis e acesso**
- Os perfis estão numa tabela, **sem tabela de permissões**. O acesso está ligado ao nome do perfil: `->middleware('perfil:rececionista')`, com as constantes em `Perfil::NOMES`.
- Um perfil novo criado em tempo de execução não teria acesso a nada. "Criar perfis" foi interpretado como **atribuir um perfil existente** (confirmado). Dizê-lo no cenário do UC24 do relatório.

**Contas e fichas**
- Conta (`users`) separada da ficha (`pacientes` / `profissionais`). Cada conta tem no máximo uma ficha (`user_id` único).
- O paciente pode existir sem conta (ficha de balcão).
- **O registo nunca se apropria de uma ficha de balcão:** se o nº de utente já existir, o registo é recusado com "dirija-se à receção". Quem associa a conta à ficha é a receção (por fazer, no #58).
- Nº de utente obrigatório e único (9 dígitos). Cédula única por tipo: `UNIQUE (tipo, num_cedula)`, porque as Ordens dos Médicos e dos Enfermeiros numeram de forma independente.
- O tipo de profissional fica só em `profissionais.tipo`. O perfil é sempre `profissional`.
- **Admin:**
  - não muda o perfil de contas com ficha de paciente ou de profissional (a ficha ficaria órfã);
  - não se desativa nem muda o próprio perfil;
  - reativar uma conta limpa o bloqueio.

**Consultas**
- Duração fixa de 30 min. A consulta guarda o profissional, a especialidade e a hora de início.
- A especialidade tem de ser uma das do profissional, com FK composta para `especialidade_profissional`.
- Horário **semanal que se repete** (`dia_semana` 1 a 7, horas certas ou meias). Sem sobreposições no mesmo dia, regra verificada na aplicação porque o MySQL não a exprime num CHECK.
- **Estados:** `em_espera`, `pedida`, `confirmada`, `rejeitada`, `cancelada`, `realizada`, `faltou`. **Ocupam vaga:** `pedida`, `confirmada`, `realizada` e `faltou` (uma falta consome a vaga).
- **Matriz de transições** (`EstadoConsulta::transicoesPermitidas()`):
  - em_espera → pedida | cancelada
  - pedida → confirmada | rejeitada | cancelada
  - confirmada → realizada | faltou | cancelada
  - rejeitada, cancelada, realizada e faltou são estados terminais
  - Uma consulta só nasce como `pedida` ou `em_espera`.
- Quem pode fazer cada transição é autorização e fica nos casos de uso, não na matriz. Corrigir um registo de realizada ou falta não é possível; se o cenário 3.4.4 o pedir, decide-se quem o faz.
- **Lista de espera = estado** `em_espera`, ordenado por `data_pedido`, não uma tabela própria. O sistema promove o primeiro da lista (fluxo ainda por fazer, no #75).
- **Receção marca consulta:** nasce `pedida` e passa logo a `confirmada` na mesma transação; o histórico regista os dois passos.
- **Paciente pede:** fica `pedida` até a receção confirmar. Rejeitar exige motivo.
- **Registar realizada ou falta (profissional):** só em consultas confirmadas, só depois da hora de início e só nas consultas do próprio.
- Consultas e histórico usam `data_pedido`, `data_estado` e `data_hora` em vez de timestamps. `consultas.estado` repete o último registo do histórico, e ambos são escritos na mesma transação.

**Nomes e convenções**
- No código seguem-se as convenções do Laravel (`users`, `id`, `paciente_id`, plural). O ER do relatório mantém os nomes em português, com uma nota de mapeamento.
- `Perfil` e `Profissional` têm `#[Table]` explícito, porque o Laravel pluralizaria para `perfils` e `profissionals`.
- **Idioma:** PT por omissão, EN alternativo, fallback `en`. Os textos escrevem-se em PT com `__()` e a tradução vai para `lang/en.json`. `lang/pt/validation.php` tem nomes PT para os campos próprios e usa "palavra-passe", não "senha".
- **Página de disponibilidade pública,** filtrada por especialidade. Devolve só as horas das vagas, sem dados de pacientes, com limite de 60 pedidos por minuto.

## 5. O que está implementado (`main`, 293 testes verdes)

> Atualizado a 16 nov. 2026. As secções 1 a 4 mantêm-se; a secção 3 descreve a forma de trabalhar antiga (patches). Desde 15 nov. o Claude trabalha numa sessão na cloud com PHP 8.4 em Docker, faz push para um branch `claude/*` e o Diogo abre os PRs com `git cherry-pick` (ver `.ai/guidelines/clinica.md`).

**Decisões:** `docs/decisoes.md` vai até à **D37**. As mais recentes: D31 reagendar cria consulta nova, D32/D33 lista de espera, D34 prazos de cancelamento, D35 registo e associação de conta, D36 notificações, D37 observações e documentos.

**Esquema (além do que já existia):** `consultas.reagendada_de_id`; vistas `v_consultas_detalhe`, `v_estatisticas_estado`, `v_estatisticas_profissional`, `v_estatisticas_especialidade`; tabelas `notifications`, `observacoes` e `documentos`.

**Peças de arquitetura:**
- `App\Services\Marcacoes`: pedir, marcar, confirmar, rejeitar, cancelar, reagendar, lista de espera e promoção. Único sítio com regras de negócio de consultas.
- `App\Services\Avisos`: quem é notificado de quê. `App\Services\Estatisticas`: leitura das vistas. `App\Services\Disponibilidade`: vagas livres e ocupadas.
- Policies: `ConsultaPolicy`, `HorarioPolicy`, `PacientePolicy`, `DocumentoPolicy`, aplicadas nas rotas com `->can()`.
- `TransicaoEstadoInvalida` respondida em `bootstrap/app.php` (409 em JSON, `back()` com erro fora de JSON).
- Factories para todos os modelos; a `ConsultaFactory` cria o histórico completo de cada estado.
- CI em `.github/workflows/testes.yml` (PHP 8.3 e 8.4, SQLite), em cada PR para o `main`.

**Funcionalidades por perfil:** ver a tabela do `README.md`.

## 6. O que falta

- **#37 seeders com dados de demonstração** (Timóteo). Sem eles, a demo e os testes manuais começam com a base de dados vazia.
- **#79 teste de concorrência** e **#80 medição de desempenho**: medições para o relatório.
- **Relatório:** o capítulo 3 e o diagrama ER estão desatualizados face ao código (atores da D26, sete estados, tabelas novas). As diferenças foram enviadas ao Filipe por mensagem.
- **#84:** dump da base de dados, zip do código e submissão.

## 7. Pendências e riscos

- **PHP:** o projeto é 8.4 (D01). O `CLAUDE.md` do `main` foi gerado numa máquina com 8.5 e ainda o diz; corrige-se com `php artisan boost:update` numa máquina em 8.4.
- **Limite de upload:** os documentos aceitam 5 MB, mas o PHP vem com 2 MB. Está explicado no README.
- **Concorrência:** a promoção da lista de espera usa `lockForUpdate()`, mas a suite não simula dois pedidos em simultâneo. É o que o #79 deve cobrir.
- **Numeração dos UC:** as issues e o relatório usam números diferentes. Citar pela issue (`#NN`) ou pelo nome.
- **Menu da receção:** entre 992 e 1200 px parte em duas linhas.
