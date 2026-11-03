# Registo de decisões técnicas

Este documento regista as decisões técnicas do projeto: o que foi decidido, porquê e que alternativa ficou de fora. Serve para:
- qualquer elemento do grupo saber justificar uma escolha na defesa;
- o relatório ter uma fonte única para as justificações;
- não voltar a discutir o que já está fechado.

A partir de 3 de novembro de 2026, as decisões técnicas são tomadas pelo Diogo e explicadas aqui ao resto do grupo. Para mudar uma decisão, acrescenta-se uma nova entrada que substitui a anterior. As entradas antigas não se apagam.

| # | Decisão | Área |
|---|---|---|
| D01 | Laravel 13 + PHP 8.4 + MySQL 8.0 | Stack |
| D02 | Páginas geradas no servidor (Blade + Bootstrap 5), com AJAX nas ações | Interface |
| D03 | Multilingue PT/EN guardado na sessão | Interface |
| D04 | Âmbito alargado a "profissional de saúde" (médico ou enfermeiro) | Âmbito |
| D05 | Regras do ciclo de vida da consulta | Âmbito |
| D06 | Consultas com duração fixa de 30 minutos | Modelo de dados |
| D07 | Autenticação feita à mão, sem starter kit nem Fortify | Segurança |
| D08 | Perfis numa tabela `PERFIL`, sem tabelas de permissões | Modelo de dados |
| D09 | Separação entre conta (`UTILIZADOR`) e papel (`PACIENTE`, `PROFISSIONAL`) | Modelo de dados |
| D10 | Nome em `UTILIZADOR` e em `PACIENTE`, sincronizados por transação | Modelo de dados |
| D11 | Pacientes podem existir sem conta; associação feita pelo rececionista | Modelo de dados |
| D12 | Bloqueio de login guardado na base de dados | Segurança |
| D13 | Profissionais com várias especialidades | Modelo de dados |
| D14 | A consulta guarda o profissional e a hora de início | Modelo de dados |
| D15 | Restrição anti-duplicação com coluna gerada | Modelo de dados |
| D16 | Horário de atendimento semanal que se repete | Modelo de dados |
| D17 | Fluxo de trabalho no GitHub | Processo |

---

## D01 — Laravel 13 + PHP 8.4 + MySQL 8.0

**Decisão:** backend em Laravel 13 (PHP 8.4), base de dados MySQL 8.0, desenvolvimento em Windows com Laravel Herd.

**Porquê:**
- Nenhum elemento do grupo tinha usado frameworks. O Laravel traz já prontos o MVC, as rotas, as migrations, os seeds, a validação e o multilingue, que noutra stack teriam de ser montados à mão.
- Os três elementos usam Windows, e o Herd instala PHP e Composer num só passo.
- O enunciado só obriga a PDO/MySQLi se se usar PHP. O Laravel usa PDO internamente, por isso fica cumprido.

**Alternativa rejeitada:** Node.js + Express. Exigia escolher e ligar à mão o router, o motor de vistas, as sessões, o multilingue e as migrations. São mais decisões e mais pontos de falha para um grupo sem experiência.

---

## D02 — Páginas geradas no servidor, com AJAX nas ações

**Decisão:** vistas Blade com Bootstrap 5. As ações que não precisam de mudar de página (consultar vagas, pedir, confirmar ou cancelar consultas) usam AJAX com respostas JSON. Não há SPA (React, Vue).

**Porquê:**
- Cumpre o requisito de AJAX sem obrigar o grupo a aprender um framework de frontend.
- O Bootstrap substituiu o Tailwind que vem com o Laravel: tem componentes prontos (menus, formulários, modais) e é responsivo sem escrever CSS (RNF05).

**Alternativa rejeitada:** SPA em React ou Vue. Duplicava a validação e as rotas no cliente e no servidor e acrescentava uma tecnologia nova ao grupo.

---

## D03 — Multilingue PT/EN guardado na sessão

**Decisão:** português por defeito e inglês como alternativa. O idioma escolhido fica na sessão, e um middleware (`SetLocale`) aplica-o a cada pedido. As mensagens de validação em português vêm do pacote `laravel-lang/common`.

**Porquê:** cumpre o RNF07 sem obrigar a ter conta para mudar de idioma. Os textos da interface ficam em `lang/en.json`.

**Alternativa rejeitada:** idioma no URL (`/en/...`). Obrigava a duplicar todas as rotas.

**Consequência:** todo o texto novo da interface tem de ter tradução em `lang/en.json`. Isto faz parte da checklist do PR.

---

## D04 — Âmbito alargado a "profissional de saúde"

**Decisão:** o ator "médico" passa a "profissional de saúde", que pode ser médico ou enfermeiro.

**Porquê:** o professor pediu para incluir a enfermagem. Generalizar o ator evita duplicar casos de uso para médicos e enfermeiros.

**Consequência:** o diagrama de casos de uso, a tabela de atores e o modelo de dados usam "profissional de saúde". O pedido de consulta tem duas variantes (UC06 médica e UC07 enfermagem), mas ambas usam a mesma tabela `CONSULTA`.

---

## D05 — Regras do ciclo de vida da consulta

**Decisão:**
- O rececionista confirma ou rejeita os pedidos de consulta.
- O paciente e o profissional de saúde também podem cancelar.
- A lista de espera é gerida pelo sistema, que dá prioridade a quem fez o pedido primeiro.

**Porquê:** é a divisão de responsabilidades descrita na especificação. Deixa ao rececionista o controlo da agenda, sem impedir quem tem a consulta de a cancelar.

---

## D06 — Consultas com duração fixa de 30 minutos

**Decisão:** todas as consultas duram 30 minutos. As vagas são calculadas em blocos de 30 minutos dentro do horário de atendimento.

**Porquê:** simplifica o cálculo de vagas e a restrição anti-duplicação (D15). Não é preciso guardar a hora de fim nem verificar sobreposições parciais entre consultas.

**Alternativa rejeitada:** duração variável por especialidade. Obrigava a verificar sobreposições de intervalos, o que não se resolve com uma restrição única na base de dados.

---

## D07 — Autenticação feita à mão

**Decisão:** o registo, o login, o logout e o bloqueio são implementados com as peças base do Laravel (`Auth`, `Hash`, sessões), sem starter kit nem Fortify.

**Porquê:**
- Os starter kits do Laravel trazem Tailwind ou um frontend em React, Vue ou Livewire, e entram em conflito com o layout Bootstrap (D02).
- O Fortify esconde a lógica. Na defesa é preciso saber explicar como a palavra-passe é verificada e como funciona o bloqueio, e isso é mais fácil com código escrito pelo grupo.
- As palavras-passe são guardadas com o hash do Laravel (bcrypt), o que cumpre o RNF01.

**Alternativa rejeitada:** Laravel Fortify. Funcionava, mas era mais difícil de explicar.

---

## D08 — Perfis numa tabela `PERFIL`, sem tabelas de permissões

**Decisão:** existe uma tabela `PERFIL` (paciente, profissional, rececionista, administrador), e cada utilizador tem exatamente um perfil. As tabelas `PERMISSAO` e `PERFIL_PERMISSAO` foram retiradas do modelo. O acesso é controlado por um middleware que verifica o perfil em todas as rotas (RNF02).

**Porquê:** os perfis são fixos e cada um tem um conjunto fixo de funcionalidades. Tabelas de permissões que nenhum código lê seriam modelo sem uso, e seria difícil justificá-las.

**Alternativa rejeitada:** permissões configuráveis por perfil. Só faria sentido se o administrador pudesse alterar permissões pela interface, e isso não é pedido.

**Consequência:** o UC25 passa a ser "Atribuir perfil".

---

## D09 — Separação entre conta e papel

**Decisão:**
- `UTILIZADOR` é a conta de acesso: email, palavra-passe, perfil, ativo e bloqueio.
- Os dados de cada papel ficam em tabelas próprias: `PACIENTE` e `PROFISSIONAL` (cédula, especialidades).
- O rececionista e o administrador só têm a linha em `UTILIZADOR`.
- Cada conta corresponde, no máximo, a um paciente ou a um profissional (`id_utilizador` único nas duas tabelas).

**Porquê:** os perfis precisam de dados diferentes. Uma tabela única teria muitas colunas vazias e não permitiria pacientes sem conta (D11).

---

## D10 — Nome em `UTILIZADOR` e em `PACIENTE`

**Decisão:**
- `UTILIZADOR` tem `nome`, para todos os perfis.
- `PACIENTE` mantém `nome` e `email_contacto`, porque um paciente de balcão não tem conta.
- `PROFISSIONAL` deixa de ter `nome`, porque um profissional tem sempre conta.
- Para um paciente com conta, o registo (UC04) e a edição de dados (UC10) gravam o nome nas duas tabelas **dentro da mesma transação**.

**Porquê:** o rececionista e o administrador também precisam de nome (por exemplo, para a barra de navegação). A duplicação no paciente é o preço de permitir fichas sem conta, e a transação garante que as duas cópias nunca ficam diferentes.

**Alternativa rejeitada:** nome só no `PACIENTE` e no `PROFISSIONAL`. O rececionista e o administrador ficavam sem nome.

---

## D11 — Pacientes podem existir sem conta

**Decisão:**
- `PACIENTE.id_utilizador` pode ser nulo, para permitir fichas criadas ao balcão ou por telefone (UC17, UC20).
- O registo (UC04) cria sempre um paciente novo e nunca associa automaticamente a uma ficha existente.
- A associação de uma ficha a uma conta é feita pelo rececionista no UC20.

**Porquê:** associar automaticamente pelo email seria uma falha de privacidade. Como o email não é verificado, qualquer pessoa podia registar-se com o email de outro paciente e passar a ver o histórico dele, violando o RNF03.

**Alternativa rejeitada:** associação automática pelo email. Só seria segura com verificação do email.

---

## D12 — Bloqueio de login guardado na base de dados

**Decisão:** `UTILIZADOR` tem `tentativas_falhadas` e `bloqueado_ate`. Depois de um número fixo de tentativas falhadas, a conta fica bloqueada durante um período. Uma conta com `ativo = falso` nunca entra.

**Porquê:** o bloqueio fica visível no modelo de dados e no relatório, e é fácil de demonstrar e de explicar.

**Alternativa rejeitada:** `RateLimiter` do Laravel, que guarda os contadores em cache, fora da base de dados.

**Limitação conhecida:** como o bloqueio é por conta, alguém que saiba o email de outra pessoa pode bloquear-lhe a conta temporariamente. Para este projeto é aceitável.

---

## D13 — Profissionais com várias especialidades

**Decisão:** a relação entre profissional e especialidade é de muitos para muitos (`PROFISSIONAL_ESPECIALIDADE`). Os enfermeiros têm a especialidade "Enfermagem".

**Porquê:** um médico pode ter mais de uma especialidade. Tratar a enfermagem como especialidade permite que a pesquisa de disponibilidade funcione da mesma forma para médicos e enfermeiros.

**Consequência:** a consulta tem de guardar a especialidade (D14), e a aplicação valida que é uma das especialidades do profissional.

---

## D14 — A consulta guarda o profissional e a hora de início

**Decisão:** `CONSULTA` tem `id_profissional`, `inicio` (data e hora) e `id_especialidade`. Deixa de ter `id_horario` e `tipo`.

**Porquê:**
- Com `id_horario`, a consulta não sabia a que horas era, porque um horário é um período com várias vagas.
- O `tipo` (médica ou enfermagem) já se obtém a partir do profissional, por isso guardá-lo era duplicação.
- Guardar a hora de início permite a restrição anti-duplicação (D15).

**Validações feitas pela aplicação, na transação de marcação:**
- o `inicio` cai dentro de um período de atendimento do profissional e corresponde a uma vaga de 30 minutos;
- a especialidade é uma das do profissional.

---

## D15 — Restrição anti-duplicação com coluna gerada

**Decisão:**
```sql
vaga_ocupada TINYINT GENERATED ALWAYS AS (IF(estado <> 'Cancelada', 1, NULL)) VIRTUAL,
UNIQUE (id_profissional, inicio, vaga_ocupada)
```

**Porquê:**
- O RNF04 exige que a base de dados impeça duas marcações na mesma vaga e que uma vaga cancelada volte a ficar livre.
- O MySQL não tem índices únicos parciais (por exemplo, "único só se não estiver cancelada"). Numa consulta cancelada, a coluna gerada fica `NULL`, e o MySQL aceita vários `NULL` num índice único. Assim, a vaga volta a ficar disponível.
- Como a restrição está na base de dados, dois pedidos simultâneos para a mesma vaga não passam ambos, mesmo que a verificação na aplicação falhe. O teste de concorrência demonstra isto.

**Alternativa rejeitada:** verificar apenas na aplicação. Dois pedidos ao mesmo tempo podiam passar ambos na verificação antes de qualquer um gravar.

---

## D16 — Horário de atendimento semanal que se repete

**Decisão:** `HORARIO` tem `dia_semana` (1 = segunda … 7 = domingo), `hora_inicio` e `hora_fim`. O mesmo horário repete-se todas as semanas.

**Porquê:** é como uma clínica funciona na prática. Evita que o profissional tenha de criar períodos dia a dia.

**Alternativa rejeitada:** períodos com data e hora concretas (`inicio` e `fim` em datetime). Exigia criar cada dia à mão.

**Consequências:**
- Férias e faltas pontuais não alteram o horário. As consultas afetadas são canceladas pelo UC14.
- O UC12 impede períodos sobrepostos do mesmo profissional no mesmo dia da semana.
- Um período não pode ser removido se houver consultas futuras marcadas nesse dia da semana e nessa faixa horária.

---

## D17 — Fluxo de trabalho no GitHub

**Decisão:**
- O `main` está protegido. Todo o código entra por pull request com review de outro elemento.
- Um branch por issue: `feat/`, `fix/`, `docs/` ou `test/`, seguido do número da issue e de uma descrição.
- Commits no formato `tipo: descrição`, e cada commit faz uma só coisa.
- Antes de abrir um PR, o branch é atualizado com o `main` e todos os testes passam.

**Porquê:** é a prova do desenvolvimento colaborativo pedida no enunciado e evita que alterações sem relação se misturem.
