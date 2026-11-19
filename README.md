# Primeira instalação e teste das funcionalidades

Guia passo a passo para pôr a Clínica a funcionar numa máquina Windows com Laravel Herd, a partir do zero, e para percorrer todas as funcionalidades no browser.

O `README.md` tem a versão curta da instalação. Este guia é a versão detalhada; se os dois divergirem, vale o `README.md` e este ficheiro tem de ser corrigido.

**Tempo previsto:** 30 a 45 minutos para instalar, mais cerca de 45 minutos para o roteiro de testes completo.

## Índice

1. [O que vai ser instalado](#1-o-que-vai-ser-instalado)
2. [Instalar as ferramentas](#2-instalar-as-ferramentas)
3. [Preparar o Herd](#3-preparar-o-herd)
4. [Criar a base de dados](#4-criar-a-base-de-dados)
5. [Obter e configurar o código](#5-obter-e-configurar-o-código)
6. [Criar as tabelas e as contas iniciais](#6-criar-as-tabelas-e-as-contas-iniciais)
7. [Abrir a aplicação](#7-abrir-a-aplicação)
8. [Correr os testes automáticos](#8-correr-os-testes-automáticos)
9. [Roteiro de teste das funcionalidades](#9-roteiro-de-teste-das-funcionalidades)
10. [Problemas frequentes](#10-problemas-frequentes)
11. [Recomeçar do zero](#11-recomeçar-do-zero)
12. [Alojamento no AVE](#12-alojamento-no-ave)

---

## 1. O que vai ser instalado

| Ferramenta | Versão | Para quê |
|---|---|---|
| Laravel Herd | atual | Serve a aplicação em `http://clinica.test` e traz `php`, `composer`, `node` e `npm` |
| PHP | **8.4** | Vem com o Herd. As dependências estão fixadas para PHP 8.3.27, a versão do servidor AVE, e funcionam igualmente em 8.4 (D40) |
| Node | 22 ou mais recente | Vem com o Herd. Compila o CSS e o JavaScript (Vite) |
| MySQL Community Server | 8.0 | Base de dados. **Não vem com o Herd gratuito**: instala-se à parte |
| Git | qualquer | Obter o código |

Todos os comandos deste guia são para o **PowerShell**.

> **Rede do ISEP:** a rede da escola bloqueia o acesso do Composer ao Packagist. O passo `composer setup` (secção 5) tem de ser feito com o hotspot do telemóvel ou em casa.

---

## 2. Instalar as ferramentas

### 2.1 Git

1. Descarregar em <https://git-scm.com/download/win> e instalar com as opções por omissão.

### 2.2 Laravel Herd

1. Descarregar em <https://herd.laravel.com/windows> e instalar.
2. Abrir o Herd uma vez e aceitar o pedido de permissões de administrador (o Herd precisa delas para resolver os endereços `*.test`).
3. Deixar o Herd a correr: o ícone fica junto ao relógio do Windows.

### 2.3 MySQL Community Server 8.0

1. Descarregar o **MySQL Installer** em <https://dev.mysql.com/downloads/installer/> (escolher a série 8.0).
2. No instalador, escolher **Server only** (ou **Custom** e selecionar só o MySQL Server 8.0).
3. Na configuração:
   - **Type and Networking:** deixar a porta `3306`.
   - **Authentication Method:** a recomendada (*Strong Password Encryption*).
   - **Accounts and Roles:** escolher a password do `root` e **guardá-la**: vai ser precisa na secção 4.
   - **Windows Service:** deixar marcado *Start the MySQL Server at System Startup*.
4. Acrescentar a pasta dos binários ao `PATH`, para o comando `mysql` existir no PowerShell:
   - Menu Iniciar → "Editar as variáveis de ambiente do sistema" → **Variáveis de ambiente** → em *Variáveis do utilizador*, editar `Path` → **Novo** → `C:\Program Files\MySQL\MySQL Server 8.0\bin`.

### 2.4 Confirmar

Fechar todos os PowerShell abertos, abrir um **novo** e correr:

```powershell
php -v; composer -V; node -v; npm -v; git --version; mysql --version
```

Resultado esperado: `PHP 8.4.x`, Composer 2.x, Node `v22` ou superior, e uma linha de versão para o `npm`, o `git` e o `mysql`. Se algum comando não for reconhecido, ver a [secção 10](#10-problemas-frequentes).

---

## 3. Preparar o Herd

### 3.1 PHP 8.4

1. Clicar no ícone do Herd → **Settings** → separador **PHP**.
2. Se o 8.4 não estiver instalado, instalá-lo nessa lista.
3. Marcar o 8.4 como versão global (*Use as global PHP version*).
4. Confirmar num PowerShell novo: `php -v` tem de mostrar `8.4`.

Em alternativa, pela linha de comandos: `herd php:list` mostra as versões instaladas. `herd list` mostra todos os comandos disponíveis.

### 3.2 Node

1. Em **Settings** → separador **Node**, confirmar que há uma versão 22 ou superior instalada e ativa.
2. Confirmar: `node -v`.

### 3.3 Pasta dos sites

O Herd serve automaticamente cada pasta que esteja dentro da sua pasta de sites, com o nome da pasta como endereço. Por omissão é `%USERPROFILE%\Herd` (por exemplo `C:\Users\diogo\Herd`).

1. Em **Settings** → separador **General**, confirmar qual é a pasta em *Herd paths*.
2. O projeto vai ser clonado para lá, numa pasta chamada **`clinica`**. É esse nome que dá o endereço `http://clinica.test`, que é o que está em `APP_URL` no `.env.example`.

> Se o projeto ficar noutra pasta ou com outro nome, o endereço muda. Nesse caso, ou se acerta o `APP_URL` no `.env`, ou se corre `herd link clinica` dentro da pasta do projeto.

### 3.4 Limite de upload (documentos até 5 MB)

A aplicação aceita documentos até 5 MB, mas o PHP vem configurado para 2 MB.

1. **Settings** → separador **PHP** → campo do tamanho máximo de upload (*Max File Upload Size*): pôr **5** ou mais.
2. Em alternativa, correr `herd ini` (abre o `php.ini` da versão em uso, em `%USERPROFILE%\.config\herd\bin\<versão>\php.ini`) e alterar `upload_max_filesize = 5M`. O `post_max_size` por omissão (8 MB) já chega.
3. Reiniciar os serviços do Herd: ícone do Herd → **Stop all** e depois **Start all**.

### 3.5 HTTPS: não ativar

O `.env.example` usa `http://clinica.test`. Não é preciso ativar o HTTPS do Herd (*Secure*). Se for ativado, o `APP_URL` tem de passar a `https://clinica.test`.

---

## 4. Criar a base de dados

Faz-se uma vez por máquina. A aplicação usa um utilizador próprio (`clinica_app`), nunca o `root`.

1. Entrar no MySQL como `root` (pede a password escolhida na secção 2.3):

   ```powershell
   mysql -u root -p
   ```

2. Na linha `mysql>`, correr (trocar `escolhe_uma_password` por uma password à escolha e **guardá-la**):

   ```sql
   CREATE DATABASE clinica CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'clinica_app'@'localhost' IDENTIFIED BY 'escolhe_uma_password';
   GRANT ALL PRIVILEGES ON clinica.* TO 'clinica_app'@'localhost';
   FLUSH PRIVILEGES;
   EXIT;
   ```

3. Confirmar que o utilizador novo entra e vê a base de dados:

   ```powershell
   mysql -u clinica_app -p -e "SHOW DATABASES;"
   ```

   Resultado esperado: a lista inclui `clinica`.

---

## 5. Obter e configurar o código

1. Clonar para dentro da pasta do Herd, com o nome `clinica`:

   ```powershell
   cd ~\Herd
   git clone https://github.com/varzi0503/Clinica_MODSI.git clinica
   cd clinica
   ```

2. Criar o ficheiro de configuração local:

   ```powershell
   copy .env.example .env
   ```

3. Abrir o `.env` num editor e preencher **só** esta linha, com a password do `clinica_app` (secção 4):

   ```
   DB_PASSWORD=escolhe_uma_password
   ```

   O resto já vem certo: `DB_DATABASE=clinica`, `DB_USERNAME=clinica_app`, `APP_URL=http://clinica.test`.

4. Instalar tudo (**fora da rede do ISEP**):

   ```powershell
   composer setup
   ```

   O `composer setup` faz, por esta ordem:

   | Passo | Comando equivalente | O que faz |
   |---|---|---|
   | 1 | `composer install` | Instala as dependências PHP em `vendor/` |
   | 2 | (cópia do `.env`) | Só se o `.env` ainda não existir |
   | 3 | `php artisan key:generate` | Gera a `APP_KEY` desta máquina |
   | 4 | `php artisan migrate --force` | Cria as tabelas |
   | 5 | `npm install --ignore-scripts` | Instala as dependências JavaScript em `node_modules/` |
   | 6 | `npm run build` | Compila o CSS e o JS para `public/build/` |

   > **A ordem importa:** o passo 4 precisa da `DB_PASSWORD` já preenchida. Se o `composer setup` falhar a meio com um erro de acesso à base de dados, corrigir o `.env` e voltar a correr `composer setup` (pode repetir-se sem problema).

5. Confirmar que a chave foi gerada: no `.env`, a linha `APP_KEY=` tem de ter um valor a começar por `base64:`.

> **O `.env` nunca vai para o repositório.** A `APP_KEY` e a `DB_PASSWORD` são de cada máquina.

---

## 6. Criar as tabelas e as contas iniciais

```powershell
php artisan migrate:fresh --seed
```

Este comando **apaga tudo** o que estiver na base de dados `clinica`, recria as tabelas e as vistas, e cria os quatro perfis e duas contas:

| Conta | Perfil | Palavra-passe |
|---|---|---|
| `admin@example.com` | administrador | `password` |
| `test@example.com` | rececionista | `password` |

Não há pacientes, profissionais, especialidades nem consultas: os dados de demonstração são da issue #37 e ainda não existem. O roteiro da secção 9 cria à mão os dados de que precisa.

Confirmar que as tabelas existem:

```powershell
php artisan migrate:status
```

Resultado esperado: todas as migrations com `Ran`.

---

## 7. Abrir a aplicação

1. Confirmar que o Herd está a correr (ícone junto ao relógio) e que o site aparece: ícone do Herd → **Sites** → deve existir `clinica`.
2. Abrir <http://clinica.test>.
3. Resultado esperado: a página inicial da clínica, com o menu **Início · Disponibilidade · Especialidades · Profissionais** e, à direita, **Entrar** e **Criar conta**.
4. Clicar em **Entrar** e iniciar sessão com `test@example.com` / `password`. Resultado esperado: "Sessão iniciada." e o menu da receção (**Pedidos · Marcar consulta · Consultas · Agenda geral · Gestão**).

Não é preciso `php artisan serve`, nem `queue:work`, nem o agendador: o Herd serve o site e as notificações são enviadas no próprio pedido, sem fila (D36).

Os emails não são enviados de verdade: ficam escritos em `storage/logs/laravel.log` (`MAIL_MAILER=log`).

---

## 8. Correr os testes automáticos

### 8.1 Suite normal (SQLite em memória)

```powershell
npm run build
php artisan test
```

- Os testes usam uma base de dados SQLite em memória (`phpunit.xml`): **não tocam** na base de dados `clinica`.
- O `npm run build` é preciso antes: os testes de páginas leem o manifesto do Vite em `public/build/`.
- Resultado esperado: todos os testes a verde, com alguns marcados como ignorados (os que só correm em MySQL ou MariaDB).

### 8.2 Testes que só correm em MySQL (opcional)

Os testes de concorrência (issue #79) e das restrições CHECK precisam de uma base de dados MySQL verdadeira e **esvaziam as tabelas** no fim. Por isso usam uma base de dados à parte, nunca a `clinica`.

1. Criar a base de dados de teste (uma vez), como `root`:

   ```sql
   CREATE DATABASE clinica_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   GRANT ALL PRIVILEGES ON clinica_test.* TO 'clinica_app'@'localhost';
   FLUSH PRIVILEGES;
   ```

2. Correr, no PowerShell:

   ```powershell
   $env:DB_CONNECTION = 'mysql'; $env:DB_DATABASE = 'clinica_test'
   php artisan test --filter=ConcorrenciaTest
   Remove-Item Env:DB_CONNECTION, Env:DB_DATABASE
   ```

   A última linha é obrigatória: sem ela, os comandos seguintes nesse PowerShell continuam apontados à `clinica_test`.

### 8.3 Medição de desempenho (opcional)

```powershell
php artisan test --group=desempenho
```

Mede as operações habituais com cerca de 29 000 consultas (RNF06). O resultado fica em `storage/app/desempenho.md`.

---

## 9. Roteiro de teste das funcionalidades

O roteiro segue uma ordem: cada parte cria os dados de que a seguinte precisa. Começar com a base de dados acabada de criar (`php artisan migrate:fresh --seed`).

**Antes de começar:**

- Usar **duas janelas de browser separadas** (uma normal e uma anónima, ou dois browsers) para ter duas sessões ao mesmo tempo, por exemplo receção e paciente.
- Todas as palavras-passe têm de ter pelo menos 8 caracteres. O roteiro usa sempre `password`.
- O registo de contas está limitado a 5 por minuto e o login a 10 tentativas por minuto, por IP. Se aparecer o erro 429 (*Too Many Requests*), esperar um minuto.

Contas usadas no roteiro:

| Quem | Email | Criada em |
|---|---|---|
| Administrador | `admin@example.com` | Seeder |
| Rececionista | `test@example.com` | Seeder |
| Médica | `medica@example.com` | Parte B |
| Paciente 1 | `ana@example.com` | Parte D |
| Paciente 2 | `bruno@example.com` | Parte D |
| Paciente de balcão | `carla@example.com` | Parte H |

### Parte A — Administrador: contas e perfis

Entrar com `admin@example.com`.

| # | Passo | Resultado esperado |
|---|---|---|
| A1 | Menu **Utilizadores** | Lista com as duas contas do seeder |
| A2 | Criar uma conta nova: nome, `rececao2@example.com`, perfil rececionista, `password` duas vezes | A conta aparece na lista. Os únicos perfis que se podem escolher são administrador e rececionista |
| A3 | Mudar o perfil de `rececao2@example.com` para administrador e voltar a rececionista | O perfil muda na lista |
| A4 | Desativar `rececao2@example.com`. Na outra janela, tentar entrar com essa conta | "A conta está desativada. Contacte a receção." |
| A5 | Reativar a conta e voltar a tentar entrar | Entra |
| A6 | Tentar desativar a própria conta (`admin@example.com`) ou mudar-lhe o perfil | Recusado: o admin não se desativa nem muda o seu próprio perfil |
| A7 | Escrever no endereço `http://clinica.test/rececao/pedidos` | Erro 403: o admin não tem acesso à área da receção (D26) |

### Parte B — Receção: especialidades e profissionais

Entrar com `test@example.com`.

| # | Passo | Resultado esperado |
|---|---|---|
| B1 | **Gestão → Especialidades** → criar "Cardiologia" e "Enfermagem geral" | Aparecem na lista |
| B2 | Tentar criar "Cardiologia" outra vez | Erro de validação: nome repetido |
| B3 | Editar "Enfermagem geral" (mudar o nome ou a descrição) | Alteração gravada |
| B4 | **Gestão → Profissionais** → criar: nome "Dra. Marta Silva", `medica@example.com`, `password`, tipo médico, cédula `12345`, especialidade Cardiologia | Aparece na lista. Foi criada a conta e a ficha ao mesmo tempo |
| B5 | Tentar criar outro **médico** com a cédula `12345` | "Já existe um profissional deste tipo com este nº de cédula." |
| B6 | Criar um **enfermeiro** com a mesma cédula `12345` e a especialidade Enfermagem geral | Aceite: médicos e enfermeiros numeram à parte (D22) |
| B7 | Tentar criar um profissional sem escolher especialidade | "Escolha pelo menos uma especialidade." |
| B8 | Tentar apagar a especialidade "Cardiologia" | Recusado: está atribuída a um profissional |

### Parte C — Profissional: horário semanal

Entrar com `medica@example.com`.

| # | Passo | Resultado esperado |
|---|---|---|
| C1 | Menu **O meu horário** → adicionar um período para **cada dia útil**, das 09:00 às 18:00 | "Período adicionado." para cada um. Incluir o dia de hoje e o de amanhã, que são precisos nas partes seguintes |
| C2 | Adicionar um período com uma hora que não seja certa nem meia (ex.: 09:15) | "As horas têm de ser certas ou meias (ex.: 09:00 ou 09:30)." |
| C3 | Adicionar um período das 10:00 às 12:00 num dia que já tem 09:00–18:00 | "Este período sobrepõe-se a outro já definido nesse dia." |
| C4 | Adicionar um período ao sábado e removê-lo | "Período removido." |

### Parte D — Visitante: páginas públicas e registo

Terminar a sessão (ou usar a janela anónima).

| # | Passo | Resultado esperado |
|---|---|---|
| D1 | Menus **Especialidades** e **Profissionais** | Mostram o que foi criado na parte B |
| D2 | Menu **Disponibilidade** → escolher Cardiologia | Vagas de 30 minutos da Dra. Marta nos próximos 14 dias, só em horas futuras. Não aparece nenhum dado de pacientes |
| D3 | **Criar conta**: nome "Ana Costa", `ana@example.com`, nº de utente `111111111`, `password` | "Conta criada. Bem-vindo(a)!" e sessão iniciada como paciente |
| D4 | Terminar a sessão e criar outra conta: "Bruno Dias", `bruno@example.com`, nº de utente `222222222` | Conta criada |
| D5 | Tentar criar uma conta com o nº de utente `111111111` | "Já existe uma conta com este nº de utente." |
| D6 | Tentar criar uma conta com um nº de utente com menos de 9 dígitos, ou com palavras-passe diferentes | Erros de validação junto aos campos |
| D7 | Seletor de idioma no menu → English | A interface passa a inglês. Voltar a Português |

### Parte E — Paciente pede, receção confirma ou rejeita

Janela 1: `ana@example.com`. Janela 2: `test@example.com` (receção).

| # | Passo | Resultado esperado |
|---|---|---|
| E1 | Ana: **Pedir consulta** → Cardiologia → escolher uma vaga **daqui a pelo menos dois dias** (chamemos-lhe vaga X) → motivo → **Enviar pedido** | Pedido criado. Em **As minhas consultas** aparece com o estado *pedida* |
| E2 | Ana: pedir outra consulta, noutra vaga (vaga Y), também a mais de dois dias | Segundo pedido *pedida* |
| E3 | Página pública **Disponibilidade** | As vagas X e Y já não aparecem como livres |
| E4 | Receção: menu **Pedidos** | Os dois pedidos da Ana |
| E5 | Receção: **confirmar** o pedido da vaga X | Sai da lista de pedidos, sem recarregar a página (AJAX) |
| E6 | Receção: **rejeitar** o pedido da vaga Y sem escrever motivo | Erro: o motivo é obrigatório |
| E7 | Receção: rejeitar o pedido da vaga Y com motivo | Sai da lista. A vaga Y volta a aparecer livre na Disponibilidade |
| E8 | Ana: menu **Notificações** | Dois avisos por ler (confirmação e rejeição); o número no menu desaparece depois de os ver |
| E9 | Abrir `storage/logs/laravel.log` e procurar `ana@example.com` | Os dois emails estão escritos no log |

### Parte F — Lista de espera e cancelamento

Janela 1: alterna entre `ana@example.com` e `bruno@example.com`. Janela 2: receção.

| # | Passo | Resultado esperado |
|---|---|---|
| F1 | Bruno: **Pedir consulta** → Cardiologia → a vaga X (ocupada pela Ana) aparece **a tracejado** → escolhê-la | Aviso de que a vaga está ocupada e de que o pedido fica em lista de espera |
| F2 | Bruno: enviar o pedido | Em **As minhas consultas** aparece com o estado *em espera* |
| F3 | Ana: **As minhas consultas** → cancelar a consulta da vaga X (está a mais de 24 horas) | Consulta *cancelada* |
| F4 | Bruno: **As minhas consultas** e **Notificações** | O pedido passou sozinho de *em espera* a *pedida* (D32) e há um aviso disso |
| F5 | Receção: **Pedidos** → confirmar o pedido do Bruno | Confirmado |
| F6 | Receção: **Marcar consulta** → pesquisar "Ana" (a lista filtra enquanto se escreve) → Cardiologia → uma vaga **nas próximas 24 horas** (hoje mais tarde ou amanhã cedo) → marcar | A consulta nasce logo *confirmada* (D24). A Ana recebe uma notificação |
| F7 | Ana: tentar cancelar essa consulta | Recusado: faltam menos de 24 horas; a mensagem manda contactar a receção (D34) |

### Parte G — Receção: marcar, reagendar, cancelar, agenda

Entrar com `test@example.com`.

| # | Passo | Resultado esperado |
|---|---|---|
| G1 | Menu **Consultas** | As consultas futuras, com filtro por profissional e por data |
| G2 | **Reagendar** a consulta do Bruno (vaga X) para outra vaga livre | A consulta antiga fica *cancelada* e nasce uma nova *confirmada* na vaga nova (D31). O Bruno e a médica recebem um aviso |
| G3 | **Marcar consulta** para a Ana → escolher uma vaga **a tracejado** (já ocupada) | "Paciente colocado na lista de espera para …": a receção também pode pôr um paciente em lista de espera |
| G4 | **Cancelar** uma consulta sem motivo | Erro: motivo obrigatório |
| G5 | Cancelar a mesma consulta com motivo | *Cancelada*. O paciente recebe um aviso |
| G6 | Menu **Agenda geral** | As consultas de todos os profissionais, por dia |

Antes de avançar, garantir que ficam pelo menos **três consultas confirmadas** da Dra. Marta: uma a começar o mais cedo possível (a próxima vaga de hoje), outra também hoje ou amanhã, e uma a mais de um dia. Marcá-las pela receção se for preciso.

### Parte H — Receção: fichas de pacientes e associação de conta

Entrar com `test@example.com`.

| # | Passo | Resultado esperado |
|---|---|---|
| H1 | **Gestão → Pacientes** → criar ficha: "Carla Reis", nº de utente `333333333` | Ficha de balcão criada, sem conta |
| H2 | Tentar criar outra ficha com o nº `333333333` | "Já existe uma ficha com este nº de utente." |
| H3 | Pesquisar "Carla" na lista de pacientes | A lista mostra só a ficha da Carla |
| H4 | Na outra janela, sem sessão: **Criar conta** com `carla@example.com` e o nº de utente `333333333` | A conta é criada **sem ficha**, com o aviso para se dirigir à receção (D35) |
| H5 | Carla: tentar **Pedir consulta** | É encaminhada para a receção: a conta ainda não tem ficha |
| H6 | Receção: editar a ficha da Carla → associar a conta com o email `carla@example.com` | Conta associada |
| H7 | Carla: **Pedir consulta** | Já consegue pedir |
| H8 | Receção: **Marcar consulta** para um paciente só com ficha de balcão (criar outra ficha, com email de contacto) | Consulta marcada. O aviso segue só por email, para o email de contacto (ver o log) |

### Parte I — Profissional: agenda, registo, observações, documentos

Entrar com `medica@example.com`.

O registo de *realizada* ou *falta* só é aceite **depois da hora de início** da consulta. Há duas formas de o testar:

- **Esperar:** marcar pela receção a próxima vaga de hoje e esperar que a hora chegue (no máximo 30 minutos).
- **Atalho pela base de dados:** recuar um dia a consulta. Ver o número da consulta no endereço da página de detalhe (`/profissional/consultas/7` → 7) e correr:

  ```powershell
  mysql -u clinica_app -p clinica -e "UPDATE consultas SET inicio = inicio - INTERVAL 1 DAY WHERE id = 7;"
  ```

| # | Passo | Resultado esperado |
|---|---|---|
| I1 | Menu **Agenda** | As consultas confirmadas da própria médica |
| I2 | Procurar os botões de registo numa consulta que **ainda não começou** | Não aparecem: só há registo depois da hora de início |
| I3 | Numa consulta que já começou: registar como **realizada** | Estado *realizada* |
| I4 | Noutra consulta que já começou: registar como **falta** | Estado *faltou* |
| I5 | Numa consulta confirmada futura: **cancelar** sem motivo, depois com motivo | Sem motivo é recusado; com motivo fica *cancelada* e o paciente recebe um aviso |
| I6 | Abrir o detalhe de uma consulta confirmada ou realizada → escrever uma **observação** | Fica registada, com data. Não se edita nem apaga |
| I7 | No mesmo detalhe: **carregar um documento** PDF | "Documento carregado." |
| I8 | Carregar um ficheiro `.txt` ou `.exe` | Recusado: só PDF, JPG e PNG |
| I9 | Carregar um PDF com mais de 5 MB | Recusado |
| I10 | A partir de uma consulta, abrir o **histórico do paciente** | As consultas desse paciente com esta profissional |
| I11 | Menu **O meu horário** → tentar remover um período que tem consultas futuras | "Não é possível remover: há N consultas futuras neste período." |

### Parte J — Paciente: histórico, documentos e dados pessoais

Entrar com o paciente da consulta usada em I6 e I7.

| # | Passo | Resultado esperado |
|---|---|---|
| J1 | **As minhas consultas** | Próximas e passadas, com o estado de cada uma |
| J2 | Abrir o detalhe da consulta de I7 | Vê o documento da médica, "carregado por Dra. Marta Silva", e consegue descarregá-lo. **Não vê** as observações (D37) |
| J3 | Carregar um documento (JPG ou PNG) | "Documento carregado." |
| J4 | Tentar remover o documento da médica | Não é possível: só quem carregou pode remover |
| J5 | Remover o seu próprio documento | "Documento removido." |
| J6 | Clicar no próprio nome no menu (**Dados Pessoais**) → mudar o telefone | Gravado |
| J7 | Mudar a palavra-passe com a palavra-passe atual errada | Recusado |
| J8 | Mudar a palavra-passe corretamente, sair e entrar com a nova | Entra |

### Parte K — Estatísticas

Entrar com `test@example.com` → **Gestão → Estatísticas**.

| # | Passo | Resultado esperado |
|---|---|---|
| K1 | Ver os totais por estado | Batem certo com o que foi feito: pelo menos uma realizada, uma falta, uma rejeitada e várias canceladas |
| K2 | Ver os quadros por profissional e por especialidade, e os gráficos | Os números da Dra. Marta e de Cardiologia correspondem às consultas criadas |

### Parte L — Segurança e controlo de acesso

| # | Passo | Resultado esperado |
|---|---|---|
| L1 | Sem sessão, abrir `http://clinica.test/paciente/consultas` | Redireciona para **Entrar** |
| L2 | Como paciente, abrir `http://clinica.test/rececao/pedidos` e `http://clinica.test/admin/utilizadores` | Erro 403 |
| L3 | Como Ana, abrir o detalhe de uma consulta do Bruno (trocar o número no endereço `/paciente/consultas/N`) | Erro 403: a posse dos dados é verificada no servidor |
| L4 | Como Ana, abrir o endereço de um documento de uma consulta do Bruno (`/documentos/N`) | Erro 403 |
| L5 | Como receção, abrir `/documentos/N` | Erro 403: a receção não vê documentos (D37) |
| L6 | Errar a palavra-passe de `bruno@example.com` cinco vezes seguidas | "Conta bloqueada por tentativas falhadas. Tente novamente às HH:MM." (15 minutos) |
| L7 | Como admin: desativar e reativar a conta do Bruno | A reativação limpa o bloqueio: o Bruno volta a entrar |
| L8 | Com o Bruno com sessão iniciada numa janela, desativar a conta dele como admin na outra. No Bruno, abrir qualquer página protegida | É expulso da sessão |

### Parte M — Funcionamento sem JavaScript

A aplicação tem de funcionar com o JavaScript desligado (os formulários fazem um pedido normal).

1. No Chrome ou no Edge: `F12` → `Ctrl+Shift+P` → escrever "Disable JavaScript" → Enter.
2. Como receção, confirmar um pedido em **Pedidos**. Resultado esperado: a página recarrega e mostra a mensagem de sucesso.
3. Voltar a ligar o JavaScript ("Enable JavaScript").

### Parte N — Lista de espera expirada

1. Criar um pedido em lista de espera (como em F1 e F2) e recuar a data com o atalho da parte I (`UPDATE consultas SET inicio = inicio - INTERVAL 30 DAY WHERE id = N;`, com o número desse pedido).
2. Correr:

   ```powershell
   php artisan consultas:expirar-espera
   ```

3. Resultado esperado: o pedido passa a *cancelada* com o motivo "vaga expirada" (D33).

---

## 10. Problemas frequentes

| Sintoma | Causa provável | Solução |
|---|---|---|
| `php`, `composer` ou `node` não são reconhecidos | PowerShell aberto antes de instalar o Herd | Fechar e abrir um PowerShell novo. Se continuar, reiniciar o Herd |
| `npm : File ...\npm.ps1 cannot be loaded because running scripts is disabled` | O PowerShell bloqueia scripts por omissão | `Set-ExecutionPolicy -Scope CurrentUser RemoteSigned` (uma vez, responder `S`/`Y`) e abrir um PowerShell novo. Em alternativa, usar `npm.cmd` em vez de `npm` |
| `mysql` não é reconhecido | O MySQL não está instalado, ou a pasta `bin` não está no `PATH` | `Get-Service *mysql*`: se não devolver nada, instalar (secção 2.3); se devolver o serviço, falta o `PATH` (secção 2.3, passo 4) |
| `php -v` mostra 8.3 ou 8.5 | Versão global errada no Herd | Secção 3.1 |
| `composer install` fica parado ou falha a descarregar | Rede do ISEP a bloquear o Packagist | Usar o hotspot do telemóvel |
| `SQLSTATE[HY000] [1045] Access denied for user 'clinica_app'` | `DB_PASSWORD` errada ou vazia no `.env` | Corrigir o `.env` e correr `php artisan config:clear` |
| `SQLSTATE[HY000] [2002]` (ligação recusada) | O serviço do MySQL está parado | `Get-Service MySQL80` e, se estiver parado, `Start-Service MySQL80` (PowerShell como administrador) |
| `SQLSTATE[HY000] [1049] Unknown database 'clinica'` | A base de dados não foi criada | Secção 4 |
| Página com `No application encryption key has been specified` | `APP_KEY` vazia | `php artisan key:generate` |
| `Vite manifest not found` ou página sem estilos | Falta compilar os recursos | `npm run build` |
| `http://clinica.test` não abre | Herd parado, ou a pasta não está na pasta de sites, ou tem outro nome | Iniciar o Herd; confirmar em **Sites**; se for preciso, `herd link clinica` dentro da pasta do projeto |
| Erro 419 (*Page Expired*) ao submeter um formulário | Sessão expirada ou `APP_URL` diferente do endereço usado | Recarregar a página; confirmar que o `APP_URL` do `.env` é o endereço que está no browser |
| Erro 429 (*Too Many Requests*) | Limite de pedidos de login ou de registo | Esperar um minuto |
| "O carregamento falhou: o ficheiro é maior do que o servidor aceita." | Limite de upload do PHP a 2 MB | Secção 3.4 |
| Erro com `Table '...' doesn't exist` depois de um `git pull` | Há migrations novas | `php artisan migrate:fresh --seed` (apaga os dados) |
| Alterações ao `.env` não fazem efeito | Configuração em cache | `php artisan config:clear` |
| Aviso do VS Code sobre `APP_PREVIOUS_KEYS` | É opcional | Ignorar, ou acrescentar `APP_PREVIOUS_KEYS=` ao `.env` |

Para ver o erro completo de uma página que falha, abrir `storage/logs/laravel.log`.

---

## 11. Recomeçar do zero

Só os dados (mantém o código e a configuração):

```powershell
php artisan migrate:fresh --seed
```

Depois de puxar alterações do `main`:

```powershell
git switch main
git pull
composer install
npm install
npm run build
php artisan migrate:fresh --seed
php artisan test
```

Os documentos carregados ficam em `storage/app/private/`. O `migrate:fresh` apaga os registos da base de dados, mas não os ficheiros: podem apagar-se à mão.

## 12. Alojamento no AVE

A plataforma fica em <http://ave.dee.isep.ipp.pt/~1212100/> (D39, D40, D41). O HTTPS do AVE responde 503 em todo o servidor, por isso o endereço é `http://`. O servidor tem PHP 8.3 e MariaDB 10.11; a raiz web é `~/public_html`. A aplicação fica em `~/clinica`, fora da área pública, e só o conteúdo de `public/` vai para `~/public_html`, com os ficheiros de `deploy/ave/public_html/`.

A base de dados `1212100` é carregada pelo phpMyAdmin, a partir de uma exportação (`.sql`) da base de dados local sem as cláusulas `DEFINER`.

1. Repor a palavra-passe do AVE no Portal dos componentes (é diferente da do MariaDB).
2. Gerar o pacote, na pasta do projeto, no `main` atualizado (pede a palavra-passe do MariaDB):

   ```powershell
   powershell -ExecutionPolicy Bypass -File deploy\ave\empacotar.ps1
   ```

3. Enviar e entrar no servidor (a opção evita o erro *Too many authentication failures*):

   ```powershell
   scp -o PubkeyAuthentication=no "$HOME\clinica-ave.tar.gz" 1212100@ave.dee.isep.ipp.pt:
   ssh -o PubkeyAuthentication=no 1212100@ave.dee.isep.ipp.pt
   ```

4. No servidor (substitui o que estiver em `~/clinica` e `~/public_html`):

   ```bash
   rm -rf ~/clinica && tar -xzf ~/clinica-ave.tar.gz -C ~ && rm ~/clinica-ave.tar.gz
   chmod 711 ~ && chmod -R a+rwX ~/clinica/storage ~/clinica/bootstrap/cache
   chcon -R -t httpd_user_content_t ~/public_html ~/clinica
   chcon -R -t httpd_user_rw_content_t ~/clinica/storage ~/clinica/bootstrap/cache
   cd ~/clinica && php -v && php artisan migrate:status && php artisan optimize
   ```

5. Abrir `http://ave.dee.isep.ipp.pt/~1212100/verifica.php` (versão do PHP, extensões, limites de upload, pastas graváveis), e depois apagá-lo: `rm ~/public_html/verifica.php`.
6. Apagar o pacote no Windows, porque contém o `.env` com a palavra-passe:

   ```powershell
   Remove-Item "$HOME\clinica-ave.tar.gz", "$HOME\clinica-ave" -Recurse -Force
   ```

O `chcon` muda as etiquetas SELinux do Fedora: sem elas, o Apache não lê os ficheiros da pasta pessoal nem grava em `storage/`. Para testar no próprio servidor, usar `curl http://ave.dee.isep.ipp.pt/~1212100/` e não `localhost`, que aponta para outro site.

Para atualizar o código, repetir os passos 2 a 5. O `rm -rf ~/clinica` apaga também os documentos carregados no servidor (`storage/app/private`). Para atualizar só os dados, reimportar o `.sql` no phpMyAdmin, apagando antes as tabelas e as vistas.
