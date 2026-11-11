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
| D15 | Restrição anti-duplicação com coluna gerada (substituída por D20) | Modelo de dados |
| D16 | Horário de atendimento semanal que se repete | Modelo de dados |
| D17 | Fluxo de trabalho no GitHub (substituída por D29) | Processo |
| D18 | Nomes no código seguem as convenções do Laravel | Modelo de dados |
| D19 | Estados da consulta e matriz de transições | Âmbito |
| D20 | Restrição anti-duplicação revista (substitui D15) | Modelo de dados |
| D21 | Lista de espera como estado da consulta | Modelo de dados |
| D22 | Nº de utente e cédula como identificadores únicos | Modelo de dados |
| D23 | Histórico de estados escrito na mesma transação que a consulta | Modelo de dados |
| D24 | Marcação pela receção nasce confirmada; pedido do paciente fica pendente | Âmbito |
| D25 | Registo de consulta realizada ou falta | Âmbito |
| D26 | Responsabilidades do administrador e da receção | Âmbito |
| D27 | Disponibilidade pública, por especialidade | Interface |
| D28 | Convenção das respostas AJAX | Interface |
| D29 | Fluxo de trabalho revisto (substitui D17) | Processo |
| D30 | Traduções: fallback em inglês e sem `trans_choice` | Interface |

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

> **Substituída por D20** (11 nov. 2026). Mantida como registo histórico.

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

> **Substituída por D29** (11 nov. 2026). Mantida como registo histórico.

**Decisão:**
- O `main` está protegido. Todo o código entra por pull request com review de outro elemento.
- Um branch por issue: `feat/`, `fix/`, `docs/` ou `test/`, seguido do número da issue e de uma descrição.
- Commits no formato `tipo: descrição`, e cada commit faz uma só coisa.
- Antes de abrir um PR, o branch é atualizado com o `main` e todos os testes passam.

**Porquê:** é a prova do desenvolvimento colaborativo pedida no enunciado e evita que alterações sem relação se misturem.


---

## D18 — Nomes no código seguem as convenções do Laravel

**Decisão:** no código, as tabelas e colunas seguem as convenções do Laravel: tabelas no plural, chave `id`, chaves estrangeiras `paciente_id`, e a tabela de contas é a `users` (com `name` e `password`). O modelo ER do relatório mantém os nomes em português, com uma nota de mapeamento (por exemplo, `UTILIZADOR.id_utilizador` corresponde a `users.id`).

**Porquê:** o Laravel deduz tabelas, chaves e relações a partir destes nomes. Contrariá-los obrigava a configurar `$table`, `$primaryKey` e as chaves estrangeiras à mão em cada modelo e em cada relação, com mais código e mais pontos de erro.

**Consequência:** `Perfil` e `Profissional` indicam a tabela explicitamente (`#[Table]`), porque o Laravel pluraliza em inglês (`perfils`, `profissionals`).

---

## D19 — Estados da consulta e matriz de transições

**Decisão:**
- Estados: `em_espera`, `pedida`, `confirmada`, `rejeitada`, `cancelada`, `realizada` e `faltou`.
- Transições permitidas:
  - `em_espera` → `pedida` ou `cancelada`
  - `pedida` → `confirmada`, `rejeitada` ou `cancelada`
  - `confirmada` → `realizada`, `faltou` ou `cancelada`
  - `rejeitada`, `cancelada`, `realizada` e `faltou` são terminais.
- Uma consulta só nasce `pedida` ou `em_espera`.
- A matriz está em `EstadoConsulta::transicoesPermitidas()`. Qualquer mudança de estado passa por `Consulta::mudarEstado()`, que recusa transições fora da matriz com `TransicaoEstadoInvalida`.

**Porquê:**
- **Rejeitar não é cancelar:** o ator é diferente (a receção recusa um pedido; o paciente ou o profissional desistem de uma consulta) e o histórico deve distinguir os dois casos.
- **Uma falta não é uma consulta cancelada:** o UC de registo de faltas e as estatísticas precisam de a contar à parte.
- **Uma regra num só sítio:** os casos de uso não podem, por engano, pôr uma consulta realizada outra vez como pedida.

**Consequência:** a matriz não sabe quem faz a transição. Quem pode confirmar, cancelar ou registar é uma regra de autorização de cada caso de uso.

---

## D20 — Restrição anti-duplicação revista (substitui D15)

**Decisão:**
```sql
vaga_ocupada TINYINT GENERATED ALWAYS AS
  (CASE WHEN estado IN ('pedida', 'confirmada', 'realizada', 'faltou') THEN 1 ELSE NULL END) STORED,
UNIQUE (profissional_id, inicio, vaga_ocupada),
UNIQUE (paciente_id, inicio, vaga_ocupada)
```
Acresce uma chave estrangeira composta `(profissional_id, especialidade_id)` para `especialidade_profissional`, e restrições CHECK em MySQL que obrigam `inicio` a ser uma hora certa ou meia.

**Porquê:**
- Com os estados da D19, "não cancelada" deixou de ser o critério certo. Pedidos em espera e rejeitados também não podem ocupar a vaga, e uma falta continua a ocupá-la.
- A segunda restrição impede o mesmo paciente de ter duas consultas à mesma hora com profissionais diferentes.
- A chave composta garante na base de dados que a especialidade da consulta é uma das que o profissional exerce. A D14 deixava essa verificação só na aplicação.
- `STORED` em vez de `VIRTUAL`: o índice único fica sobre um valor guardado em disco, e o SQLite usado nos testes também o suporta.

**Consequência:** `EstadoConsulta::ocupaVaga()` tem de coincidir com a fórmula da coluna. Um teste compara os dois nos sete estados.

---

## D21 — Lista de espera como estado da consulta

**Decisão:** um pedido para uma vaga já ocupada fica `em_espera`. A ordem da lista é a de `data_pedido`. Quando a vaga se liberta, o sistema promove o primeiro da lista para `pedida`, e o histórico regista a mudança sem utilizador (`user_id` nulo).

**Porquê:** o pedido em espera tem os mesmos dados que um pedido normal (paciente, profissional, especialidade, vaga). Uma tabela própria duplicava estes dados e obrigava a mover registos entre tabelas na promoção.

**Alternativa rejeitada:** uma tabela `LISTA_ESPERA` separada.

**Estado:** o estado `em_espera` e a ordenação já existem na base de dados. O pedido de uma vaga ocupada e a promoção automática ficam para a issue #75.

---

## D22 — Nº de utente e cédula como identificadores únicos

**Decisão:**
- `pacientes.num_utente` é obrigatório e único, com 9 dígitos.
- A cédula profissional é única por tipo: `UNIQUE (tipo, num_cedula)`.
- O tipo de profissional (médico ou enfermeiro) está só em `profissionais.tipo`. O perfil é sempre `profissional`.

**Porquê:**
- Sem um identificador único, a receção não consegue associar uma conta à ficha certa (D11) nem evitar fichas duplicadas da mesma pessoa.
- As Ordens dos Médicos e dos Enfermeiros numeram de forma independente, por isso a mesma cédula pode existir nas duas.
- O tipo guardado em três sítios (tipo, especialidade "Enfermagem" e perfil) podia ficar inconsistente.

**Limitação conhecida:** um paciente sem número do SNS (por exemplo, um estrangeiro) não pode ser registado.

---

## D23 — Histórico de estados escrito na mesma transação que a consulta

**Decisão:** `consultas.estado` e `consultas.data_estado` repetem o último registo de `historico_estados`. Ambos são escritos na mesma transação, por `Consulta::registarPedido()` e `Consulta::mudarEstado()`. A consulta e o histórico usam datas próprias (`data_pedido`, `data_estado`, `data_hora`) em vez de `created_at`/`updated_at`.

**Porquê:** as listas e a agenda precisam do estado atual sem ler o histórico inteiro. A transação garante que as duas cópias nunca ficam diferentes.

---

## D24 — Marcação pela receção nasce confirmada; pedido do paciente fica pendente

**Decisão:**
- Quando é a receção a marcar, a consulta nasce `pedida` e passa logo a `confirmada` na mesma transação. O histórico regista os dois passos com a rececionista como autora.
- Quando é o paciente a pedir, a consulta fica `pedida` até a receção a confirmar ou rejeitar.
- A rejeição exige motivo, que fica no histórico.
- Em ambos os casos o servidor volta a confirmar que a vaga está livre (`Disponibilidade::estaLivre()`). A base de dados (D20) trava os pedidos simultâneos, e o utilizador recebe uma mensagem de erro em vez de um erro 500.

**Porquê:** não faz sentido a receção aprovar o próprio pedido. Passar por `pedida` mantém a matriz da D19 sem exceções.

---

## D25 — Registo de consulta realizada ou falta

**Decisão:** só o profissional da consulta a regista como `realizada` ou `faltou`, só depois da hora de início e só se estiver `confirmada`. Uma vez registada, não se altera.

**Porquê:** registar antes da hora não faz sentido. Permitir alterações tornava o histórico e as estatísticas pouco fiáveis.

**Limitação conhecida:** não há forma de corrigir um registo feito por engano. Se for preciso, define-se quem o pode fazer (por exemplo, a receção, com motivo).

---

## D26 — Responsabilidades do administrador e da receção

**Decisão** (indicação do professor):
- **Administrador:** gere as contas, ou seja, cria, ativa e desativa contas e atribui-lhes um dos perfis existentes.
- **Receção:** cria profissionais (conta e ficha de uma vez), gere especialidades e vê estatísticas, além do que já fazia com pacientes e consultas.

Para isso:
- O administrador só cria contas e muda perfis entre `administrador` e `rececionista`.
- Contas com ficha de paciente ou de profissional não mudam de perfil. Só se ativam e desativam.
- O administrador não se desativa nem muda o próprio perfil.
- Reativar uma conta limpa o bloqueio por tentativas falhadas (D12).

**Porquê:**
- "Atribuir perfis" não inclui criar perfis novos. Sem tabela de permissões (D08), um perfil novo não teria acesso a nada.
- Uma conta de profissional ou de paciente criada sem ficha entraria e ficaria sem funcionalidades.
- Mudar o perfil de quem tem ficha deixava a ficha órfã (por exemplo, consultas de um médico que deixa de ter agenda).
- A clínica nunca pode ficar sem nenhum administrador.

**Consequência:** o UC de atribuição de perfis é descrito no relatório como "atribuir um perfil existente".

---

## D27 — Disponibilidade pública, por especialidade

**Decisão:** a página de disponibilidade não exige conta e filtra por especialidade e data. O endpoint JSON devolve só as horas de início das vagas livres (sem dados de pacientes), num horizonte de 14 dias por omissão e 31 no máximo, com limite de 60 pedidos por minuto por IP.

**Porquê:** o visitante é ator do caso de uso, e as vagas livres não são dados pessoais.

---

## D28 — Convenção das respostas AJAX

**Decisão:** os pedidos AJAX usam um helper comum (`clinica.pedirJson` em `resources/js/ajax.js`), que envia o token CSRF e `Accept: application/json` e trata a sessão expirada e as falhas de rede. As respostas seguem uma convenção:
- 2xx com `{message}`
- 422 para erros de validação
- 409 para conflitos de estado (por exemplo, um pedido já tratado por outra rececionista)
- 403 para falta de acesso

Os formulários continuam a funcionar sem JavaScript.

**Porquê:** com uma convenção única, cada caso de uso novo com AJAX reaproveita o helper em vez de repetir o tratamento de erros.

---

## D29 — Fluxo de trabalho revisto (substitui D17)

**Decisão:**
- Toda a alteração entra no `main` por pull request. O GitHub recusa pushes diretos.
- O merge não exige aprovação: o Diogo implementa o código e faz o merge.
- O Timóteo e o Filipe testam cada PR de funcionalidade **no `main`, depois do merge**, e deixam um comentário no PR com o que testaram. Os erros encontrados são abertos como issues `fix`.
- Branches, commits e testes antes do PR mantêm-se como na D17. O processo está descrito no `CONTRIBUTING.md` da raiz.

**Porquê:** desde 9 de novembro o código é escrito por um só elemento, para avançar mais depressa e sem dependências entre elementos. Exigir uma aprovação antes do merge bloqueava cada PR à espera de outro elemento. Os testes depois do merge mantêm o controlo de qualidade e deixam no repositório o registo da participação de cada um.

---

## D30 — Traduções: fallback em inglês e sem `trans_choice`

**Decisão:** o idioma de fallback é `en`. Os textos escrevem-se em português com `__()` e a tradução inglesa vai para `lang/en.json`, por ordem alfabética. Não se usa `trans_choice()`; para singular e plural usam-se duas mensagens.

**Porquê:** quando a chave não está no `pt.json`, o `trans_choice()` escolhe o idioma de fallback e mostrava a mensagem em inglês a quem tem a interface em português. O `__()` não tem este comportamento.
